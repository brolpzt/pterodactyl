<?php

namespace Pterodactyl\Http\Controllers\Api\Billing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\PaymentIntent;
use Pterodactyl\Notifications\PaymentReceived;
use Pterodactyl\Services\Billing\BillingChargeService;
use Pterodactyl\Services\Billing\WalletService;

class SicoobPixWebhookController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private BillingChargeService $billingChargeService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Sicoob PIX webhook received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'keys' => array_keys($payload),
        ]);

        $items = $this->extractEvents($payload);
        if (count($items) === 0) {
            return new JsonResponse(['ok' => true, 'processed' => 0]);
        }

        $processed = 0;
        foreach ($items as $item) {
            $processed += $this->processEvent($item);
        }

        return new JsonResponse(['ok' => true, 'processed' => $processed]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractEvents(array $payload): array
    {
        if (isset($payload['pix']) && is_array($payload['pix'])) {
            return array_map(function (array $item): array {
                $item['_status'] = 'CONCLUIDA';
                return $item;
            }, $payload['pix']);
        }

        if (isset($payload['txid']) || isset($payload['txId'])) {
            return [$payload];
        }

        return [];
    }

    /**
     * Process a single webhook event.
     *
     * @return int 1 if an intent was updated, 0 otherwise
     */
    private function processEvent(array $event): int
    {
        $txid = (string) ($event['txid'] ?? $event['txId'] ?? '');
        $status = (string) ($event['_status'] ?? $event['status'] ?? 'CONCLUIDA');
        $endToEndId = (string) ($event['endToEndId'] ?? '');
        $valor = $event['valor'] ?? null;
        $horario = $event['horario'] ?? null;

        if ($txid === '' && $endToEndId === '') {
            Log::warning('Sicoob PIX webhook ignored: missing txid and endToEndId.');
            return 0;
        }

        return DB::transaction(function () use ($txid, $status, $endToEndId, $valor, $horario, $event): int {
            $intent = $this->findIntentForWebhook($txid, $endToEndId);

            if (!$intent) {
                Log::warning('Sicoob PIX webhook intent not found', [
                    'txid' => $txid,
                    'endToEndId' => $endToEndId,
                ]);

                // ACK com 200 para evitar retry infinito no provedor.
                return 0;
            }

            $internalStatus = $this->mapSicoobStatusToInternal($status);
            $metadata = array_merge($intent->metadata ?? [], [
                'sicoob_webhook_last' => [
                    'received_at' => now()->toIso8601String(),
                    'status' => $status,
                    'txid' => $txid ?: null,
                    'end_to_end_id' => $endToEndId ?: null,
                    'valor' => $valor,
                    'horario' => $horario,
                    'raw' => $event,
                ],
            ]);

            if ($internalStatus === 'completed') {
                if ($intent->status === 'completed') {
                    return 1;
                }

                $user = $intent->user;
                if (!$user) {
                    $intent->update([
                        'status' => 'failed',
                        'metadata' => $metadata,
                    ]);

                    return 1;
                }

                // Credita o valor do intent local para manter consistencia da moeda da carteira.
                $this->walletService->deposit(
                    $user,
                    (float) $intent->amount,
                    'Sicoob PIX deposit',
                    [
                        'payment_intent_id' => $intent->id,
                        'sicoob_txid' => $txid ?: null,
                        'sicoob_end_to_end_id' => $endToEndId ?: null,
                    ]
                );

                $intent->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'metadata' => $metadata,
                ]);

                $this->billingChargeService->restoreBillingSuspendedServers($user);
                $user->notify(new PaymentReceived(
                    amount: (float) $intent->amount,
                    currency: (string) config('billing.currency', 'USD'),
                    gateway: 'pix',
                ));

                return 1;
            }

            if (in_array($internalStatus, ['cancelled', 'failed'], true) && $intent->status !== 'completed') {
                $intent->update([
                    'status' => $internalStatus,
                    'metadata' => $metadata,
                ]);

                return 1;
            }

            $intent->update(['metadata' => $metadata]);
            return 1;
        });
    }

    private function findIntentForWebhook(string $txid, string $endToEndId): ?PaymentIntent
    {
        return PaymentIntent::query()
            ->where('gateway', 'pix')
            ->where(function ($query) use ($txid, $endToEndId): void {
                if ($txid !== '') {
                    $query->where('external_id', $txid)
                        ->orWhere('metadata->pix_tax_id', $txid)
                        ->orWhere('metadata->pix_transaction_id', $txid);
                }

                if ($endToEndId !== '') {
                    $query->orWhere('metadata->sicoob_webhook_last->end_to_end_id', $endToEndId);
                }
            })
            ->lockForUpdate()
            ->first();
    }

    private function mapSicoobStatusToInternal(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'CONCLUIDA' => 'completed',
            'REMOVIDA_PELO_USUARIO_RECEBEDOR', 'REMOVIDA_PELO_PSP', 'EXPIRADA' => 'cancelled',
            'REJEITADA' => 'failed',
            default => 'pending',
        };
    }
}
