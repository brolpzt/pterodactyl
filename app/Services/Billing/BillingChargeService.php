<?php

namespace Pterodactyl\Services\Billing;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Notifications\ServerSuspendedForBilling;
use Pterodactyl\Services\Servers\SuspensionService;
use Illuminate\Support\Facades\Log;

class BillingChargeService
{
    public function __construct(
        private WalletService $walletService,
        private SuspensionService $suspensionService,
    ) {
    }

    /**
     * Charge hourly rate for a server. Suspends if insufficient balance.
     */
    public function chargeHourly(Server $server): bool
    {
        $rate = (float) ($server->hourly_rate ?? 0);
        if ($rate <= 0) {
            return true;
        }

        $user = $server->user;
        $description = "Hourly charge: {$server->name}";

        try {
            $this->walletService->charge(
                $user,
                $rate,
                $description,
                'server',
                $server->id
            );

            $server->update(['next_due_date' => now()->addHour()]);

            return true;
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Insufficient balance')) {
                $this->suspendServer($server, 'Insufficient wallet balance');
                return false;
            }
            throw $e;
        }
    }

    /**
     * Charge period amount and renew next_due_date. Suspends if insufficient balance.
     */
    public function chargePeriod(Server $server): bool
    {
        $billingType = $server->billing_type;
        $periodConfig = config("billing.period_discounts.{$billingType}");
        $periodDays = config("billing.period_days.{$billingType}");

        if (!$periodConfig || !$periodDays) {
            Log::warning("Billing: Unknown period type {$billingType} for server {$server->id}");
            return false;
        }

        $hourlyRate = (float) ($server->hourly_rate ?? 0);
        $hoursInPeriod = $periodDays * 24;
        $amount = round($hourlyRate * $hoursInPeriod * $periodConfig, 2);

        if ($amount <= 0) {
            return true;
        }

        $user = $server->user;
        $description = ucfirst(str_replace('_', ' ', $billingType)) . " renewal: {$server->name}";

        try {
            $this->walletService->charge(
                $user,
                $amount,
                $description,
                'server',
                $server->id
            );

            $server->update([
                'next_due_date' => now()->addDays($periodDays),
            ]);

            return true;
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Insufficient balance')) {
                $this->suspendServer($server, 'Insufficient wallet balance for renewal');
                return false;
            }
            throw $e;
        }
    }

    /**
     * Restore (unsuspend) servers that were suspended for billing. Call after user adds funds.
     */
    public function restoreBillingSuspendedServers(User $user): int
    {
        $servers = Server::query()
            ->where('owner_id', $user->id)
            ->where('status', Server::STATUS_SUSPENDED)
            ->whereNotNull('suspended_for_billing_at')
            ->get();

        $restored = 0;
        foreach ($servers as $server) {
            try {
                $server->update(['suspended_for_billing_at' => null]);
                $this->suspensionService->toggle($server, SuspensionService::ACTION_UNSUSPEND);
                $restored++;
            } catch (\Throwable $e) {
                Log::warning("Billing: Failed to restore server {$server->id}: {$e->getMessage()}");
            }
        }

        return $restored;
    }

    protected function suspendServer(Server $server, string $reason): void
    {
        if ($server->isSuspended()) {
            return;
        }

        Log::info("Billing: Suspending server {$server->id} ({$server->name}): {$reason}");

        try {
            $server->update(['suspended_for_billing_at' => now()]);
            $this->suspensionService->toggle($server, SuspensionService::ACTION_SUSPEND);
            $server->user->notify(new ServerSuspendedForBilling($server, $reason));
        } catch (\Throwable $e) {
            Log::error("Billing: Failed to suspend server {$server->id}: {$e->getMessage()}");
        }
    }
}
