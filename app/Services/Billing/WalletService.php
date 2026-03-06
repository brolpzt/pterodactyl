<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Wallet;
use Pterodactyl\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get or create wallet for user.
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        $wallet = Wallet::where('user_id', $user->id)->first();

        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
            ]);
        }

        return $wallet;
    }

    /**
     * Get current balance for user.
     */
    public function getBalance(User $user): float
    {
        $wallet = $this->getOrCreateWallet($user);

        return (float) $wallet->balance;
    }

    /**
     * Deposit amount into user's wallet.
     *
     * @throws \Throwable
     */
    public function deposit(User $user, float $amount, string $description = null, array $metadata = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $description, $metadata) {
            $wallet = $this->getOrCreateWallet($user);
            $wallet->balance = (float) $wallet->balance + $amount;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'description' => $description ?? 'Deposit',
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Charge (withdraw) amount from user's wallet.
     *
     * @throws \Throwable
     * @throws \RuntimeException if insufficient balance
     */
    public function charge(User $user, float $amount, string $description = null, ?string $referenceType = null, ?int $referenceId = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $description, $referenceType, $referenceId) {
            $wallet = $this->getOrCreateWallet($user);
            $balance = (float) $wallet->balance;

            if ($balance < $amount) {
                throw new \RuntimeException('Insufficient balance.');
            }

            $wallet->balance = $balance - $amount;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'charge',
                'amount' => -$amount,
                'balance_after' => $wallet->balance,
                'description' => $description ?? 'Charge',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => [],
            ]);
        });
    }

    /**
     * Admin: Add credit to user's wallet (manual adjustment).
     *
     * @throws \Throwable
     */
    public function adminAddCredit(User $user, float $amount, string $description = 'Admin credit adjustment'): WalletTransaction
    {
        return $this->deposit($user, $amount, $description, ['source' => 'admin']);
    }

    /**
     * Admin: Deduct credit from user's wallet (manual adjustment).
     *
     * @throws \Throwable
     * @throws \RuntimeException if insufficient balance
     */
    public function adminDeductCredit(User $user, float $amount, string $description = 'Admin deduction'): WalletTransaction
    {
        return $this->charge($user, $amount, $description, 'admin_adjustment', null);
    }
}
