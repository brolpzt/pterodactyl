<?php

namespace Pterodactyl\Console\Commands\Billing;

use Illuminate\Console\Command;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Billing\BillingChargeService;

class ProcessHourlyChargesCommand extends Command
{
    protected $signature = 'p:billing:process-hourly';

    protected $description = 'Process hourly billing charges for servers with billing_type=hourly.';

    public function handle(BillingChargeService $billingCharge): int
    {
        $servers = Server::query()
            ->where('billing_type', 'hourly')
            ->whereNull('status')
            ->whereNotNull('hourly_rate')
            ->where('hourly_rate', '>', 0)
            ->with('user')
            ->get();

        if ($servers->isEmpty()) {
            $this->line('No hourly servers to charge.');

            return 0;
        }

        $charged = 0;
        $suspended = 0;

        foreach ($servers as $server) {
            try {
                $ok = $billingCharge->chargeHourly($server);
                $ok ? $charged++ : $suspended++;
            } catch (\Throwable $e) {
                $this->error("Server {$server->id} ({$server->name}): {$e->getMessage()}");
            }
        }

        $this->info("Charged: {$charged}, Suspended: {$suspended}");

        return 0;
    }
}
