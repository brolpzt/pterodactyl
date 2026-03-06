<?php

namespace Pterodactyl\Console\Commands\Billing;

use Illuminate\Console\Command;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Billing\BillingChargeService;

class ProcessPeriodRenewalsCommand extends Command
{
    protected $signature = 'p:billing:process-renewals';

    protected $description = 'Process period renewals (monthly, quarterly, semi_annually, annually) for servers due.';

    public function handle(BillingChargeService $billingCharge): int
    {
        $periods = ['monthly', 'quarterly', 'semi_annually', 'annually'];

        $servers = Server::query()
            ->whereIn('billing_type', $periods)
            ->whereNull('status')
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now())
            ->whereNotNull('hourly_rate')
            ->where('hourly_rate', '>', 0)
            ->with('user')
            ->get();

        if ($servers->isEmpty()) {
            $this->line('No servers due for renewal.');

            return 0;
        }

        $renewed = 0;
        $suspended = 0;

        foreach ($servers as $server) {
            try {
                $ok = $billingCharge->chargePeriod($server);
                $ok ? $renewed++ : $suspended++;
            } catch (\Throwable $e) {
                $this->error("Server {$server->id} ({$server->name}): {$e->getMessage()}");
            }
        }

        $this->info("Renewed: {$renewed}, Suspended: {$suspended}");

        return 0;
    }
}
