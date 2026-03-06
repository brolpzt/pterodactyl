<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Billing\WalletService;
use Pterodactyl\Services\Billing\PaymentGatewayResolver;
use Pterodactyl\Http\Requests\Api\Client\Billing\DepositRequest;

class BillingController extends ClientApiController
{
    public function __construct(
        private WalletService $walletService,
        private PaymentGatewayResolver $gatewayResolver
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
            ->select('id', 'uuid', 'name', 'status')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'uuid' => $s->uuid,
                'name' => $s->name,
                'status' => $s->status,
            ]);

        return [
            'balance' => (float) $wallet->balance,
            'transactions' => $transactions,
            'servers' => $servers,
            'available_methods' => $this->gatewayResolver->getAvailableMethods(),
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

        $gateway = $this->gatewayResolver->resolve($method);
        $result = $gateway->createPayment($user, $amount, [
            'ip' => $request->ip(),
        ]);

        $wallet = $this->walletService->getOrCreateWallet($user);

        return new JsonResponse([
            'success' => true,
            'balance' => (float) $wallet->fresh()->balance,
            'intent_id' => $result['intent_id'] ?? null,
        ]);
    }
}
