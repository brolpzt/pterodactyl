<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\PlayerCountSamplingService;

class SamplePlayerCountsCommand extends Command
{
    protected $signature = 'p:maintenance:sample-player-counts
                            {--purge-only : Only delete samples older than the retention window}';

    protected $description = 'Sample online player counts for servers with GameDig configured and prune old samples.';

    public function handle(PlayerCountSamplingService $service): int
    {
        if (!$this->option('purge-only')) {
            $stats = $service->sampleAll();
            $this->line(sprintf(
                'Player count samples: sampled=%d failed=%d skipped=%d',
                $stats['sampled'],
                $stats['failed'],
                $stats['skipped']
            ));
        }

        $purged = $service->purgeOlderThanDays();
        $this->line(sprintf('Player count purge: deleted=%d', $purged));

        return self::SUCCESS;
    }
}
