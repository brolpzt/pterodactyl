<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Billing\BillingChargeService;
use Pterodactyl\Services\Billing\WalletService;
use Pterodactyl\Services\Billing\ExchangeRateService;
use Pterodactyl\Services\Billing\PaymentGatewayResolver;
use Pterodactyl\Http\Requests\Api\Client\Billing\DepositRequest;
use Pterodactyl\Support\Cpf;

class BillingController extends ClientApiController
{
    public function __construct(
        private WalletService $walletService,
        private PaymentGatewayResolver $gatewayResolver,
        private BillingChargeService $billingChargeService,
        private ExchangeRateService $exchangeRateService
    ) {
        parent::__construct();
    }

    /**
     * Get billing info: balance and recent transactions.
     */
    public function index(): array
    {
        $user = $this->request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);

        $transactions = $wallet->transactions()
            ->limit(20)
            ->get()
            ->map(function ($t) {
                $data = [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => (float) $t->amount,
                    'balance_after' => $t->balance_after ? (float) $t->balance_after : null,
                    'description' => $t->description,
                    'reference_type' => $t->reference_type,
                    'reference_id' => $t->reference_id,
                    'created_at' => $t->created_at->toIso8601String(),
                ];

                if ($t->reference_type === 'server' && $t->reference_id) {
                    $server = \Pterodactyl\Models\Server::find($t->reference_id);
                    if ($server) {
                        $data['reference'] = [
                            'server_name' => $server->name,
                            'server_uuid' => $server->uuid,
                        ];
                    }
                }

                return $data;
            });

        $servers = $user->servers()
            ->select('id', 'uuid', 'name', 'status', 'billing_type', 'hourly_rate', 'next_due_date')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'name' => $s->name,
                'status' => $s->status,
                'billing_type' => $s->billing_type,
                'hourly_rate' => $s->hourly_rate ? (float) $s->hourly_rate : null,
                'next_due_date' => $s->next_due_date?->toIso8601String(),
            ]);

        $exchangeRates = $this->exchangeRateService->getRates();

        return [
            'balance' => (float) $wallet->balance,
            'transactions' => $transactions,
            'servers' => $servers,
            'available_methods' => $this->gatewayResolver->getAvailableMethods(),
            'user_cpf' => $user->cpf,
            'exchange_rates' => $exchangeRates,
        ];
    }

    /**
     * Get exchange rates for frontend currency display (USD base).
     */
    public function exchangeRates(): array
    {
        $rates = $this->exchangeRateService->getRates();

        return [
            'exchange_rates' => $rates ?? ['USD' => 1.0, 'EUR' => 0, 'BRL' => 0, 'updated_at' => null],
        ];
    }

    /**
     * Create a deposit (add funds).
     */
    public function deposit(DepositRequest $request): JsonResponse
    {
        $user = $request->user();
        $amount = (float) $request->input('amount');
        $method = $request->input('method');
        $payerCpf = Cpf::normalize((string) $request->input('payer_cpf', ''));

        if (strtolower((string) $method) === 'pix' && Cpf::isValid($payerCpf) && $payerCpf !== (string) $user->cpf) {
            $user->forceFill(['cpf' => $payerCpf])->save();
        }

        $gateway = $this->gatewayResolver->resolve($method);
        $result = $gateway->createPayment($user, $amount, [
            'ip' => $request->ip(),
            'payer_cpf' => Cpf::isValid($payerCpf) ? $payerCpf : null,
        ]);

        $wallet = $this->walletService->getOrCreateWallet($user);

        // Restore servers only when payment completes immediately (e.g. manual gateway).
        $restoredCount = ($result['status'] ?? null) === 'completed'
            ? $this->billingChargeService->restoreBillingSuspendedServers($user)
            : 0;

        return new JsonResponse([
            'success' => true,
            'balance' => (float) $wallet->fresh()->balance,
            'intent_id' => $result['intent_id'] ?? null,
            'status' => $result['status'] ?? null,
            'gateway' => $result['gateway'] ?? $method,
            'checkout_url' => $result['checkout_url'] ?? null,
            'transaction_id' => $result['transaction_id'] ?? null,
            'tax_id' => $result['tax_id'] ?? null,
            'pix_code' => $result['pix_code'] ?? null,
            'qr_code_image' => $result['qr_code_image'] ?? null,
            'servers_restored' => $restoredCount,
        ]);
    }
}
