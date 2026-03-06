<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\User;
use Pterodactyl\Models\PaymentIntent;
use Pterodactyl\Contracts\Billing\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;

class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private WalletService $walletService
    ) {
    }

    /**
     * Manual gateway: approves immediately and credits wallet.
     */
    public function createPayment(User $user, float $amount, array $metadata = []): array
    {
        if (!config('billing.manual_enabled', false)) {
            throw new \RuntimeException('Manual payment is not enabled.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        $intent = DB::transaction(function () use ($user, $amount, $metadata) {
            $intent = PaymentIntent::create([
                'user_id' => $user->id,
                'gateway' => 'manual',
                'external_id' => 'manual-' . uniqid(),
                'amount' => $amount,
                'status' => 'completed',
                'metadata' => $metadata,
                'completed_at' => now(),
            ]);

            $this->walletService->deposit(
                $user,
                $amount,
                'Manual deposit',
                ['payment_intent_id' => $intent->id]
            );

            return $intent;
        });

        return [
            'intent_id' => $intent->id,
            'status' => 'completed',
        ];
    }

    public function getIdentifier(): string
    {
        return 'manual';
    }
}
