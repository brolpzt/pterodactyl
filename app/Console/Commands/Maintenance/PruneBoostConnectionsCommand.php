<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Services\Boost\ServerBoostService;

class PruneBoostConnectionsCommand extends Command
{
    protected $signature = 'p:maintenance:prune-boost-connections';

    protected $description = 'Delete Server Boost connection reports older than the retention window.';

    public function handle(ServerBoostService $service): int
    {
        $this->line(sprintf('Boost connections purge: deleted=%d', $service->purge()));

        return self::SUCCESS;
    }
}
