<?php

namespace Pterodactyl\Console\Commands\Server;

use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\ForcedConsoleCommandService;

class ProcessForcedConsoleCommandsCommand extends Command
{
    protected $signature = 'p:server:force-console-commands';

    protected $description = 'Re-apply egg/server forced console commands (e.g. sys_ticrate) based on ENV values.';

    public function handle(ForcedConsoleCommandService $service): int
    {
        $stats = $service->processDue();

        $this->line(sprintf(
            'Forced console commands: processed=%d sent=%d skipped=%d failed=%d',
            $stats['processed'],
            $stats['sent'],
            $stats['skipped'],
            $stats['failed']
        ));

        return self::SUCCESS;
    }
}
