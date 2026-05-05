<?php

namespace Pterodactyl\Console\Commands\Billing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Notifications\LowBalanceWarning;
use Pterodactyl\Services\Billing\WalletService;

class NotifyLowBalanceCommand extends Command
{
    protected $signature = 'p:billing:notify-low-balance';

    protected $description = 'Notify users when wallet balance is below the minimum required for active services.';

    public function __construct(
        private WalletService $walletService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $servers = Server::query()
            ->whereNull('status')
            ->whereNotNull('billing_type')
            ->where(function ($query) {
                $query->where('hourly_rate', '>', 0)->orWhere('monthly_rate', '>', 0);
            })
            ->with('user')
            ->get();

        if ($servers->isEmpty()) {
            $this->line('No active billable services found.');
            return 0;
        }

        $grouped = $servers->groupBy('owner_id');
        $notified = 0;

        foreach ($grouped as $ownerId => $userServers) {
            /** @var User|null $user */
            $user = $userServers->first()?->user;
            if (!$user) {
                continue;
            }

            $services = [];
            $requiredMinimum = 0.0;
            foreach ($userServers as $server) {
                $amount = $this->calculateNextCharge($server);
                if ($amount <= 0) {
                    continue;
                }

                $services[] = [
                    'name' => $server->name,
                    'billing_type' => (string) $server->billing_type,
                    'amount' => $amount,
                ];
                $requiredMinimum += $amount;
            }

            if ($requiredMinimum <= 0) {
                continue;
            }

            $balance = $this->walletService->getBalance($user);
            $deficit = round($requiredMinimum - $balance, 2);
            $cacheKey = $this->notificationCacheKey((int) $ownerId);

            if ($deficit <= 0) {
                Cache::forget($cacheKey);
                continue;
            }

            if (Cache::has($cacheKey)) {
                continue;
            }

            $user->notify(new LowBalanceWarning(
                balance: $balance,
                requiredMinimum: round($requiredMinimum, 2),
                deficit: $deficit,
                services: $services,
            ));

            Cache::put($cacheKey, true, now()->addHours(12));
            $notified++;
        }

        $this->info("Low balance notifications sent: {$notified}");
        return 0;
    }

    protected function calculateNextCharge(Server $server): float
    {
        $billingType = (string) $server->billing_type;

        if ($billingType === 'hourly') {
            return round((float) ($server->hourly_rate ?? 0), 2);
        }

        if (!in_array($billingType, ['monthly', 'quarterly', 'semi_annually', 'annually'], true)) {
            return 0.0;
        }

        $monthlyRate = (float) ($server->monthly_rate ?? 0);
        if ($monthlyRate > 0) {
            $multipliers = ['monthly' => 1, 'quarterly' => 3, 'semi_annually' => 6, 'annually' => 12];
            return round($monthlyRate * ($multipliers[$billingType] ?? 1), 2);
        }

        $days = (int) config("billing.period_days.{$billingType}", 0);
        if ($days <= 0) {
            return 0.0;
        }

        return round((float) ($server->hourly_rate ?? 0) * $days * 24, 2);
    }

    protected function notificationCacheKey(int $userId): string
    {
        return "billing:low-balance-warning:{$userId}";
    }
}
