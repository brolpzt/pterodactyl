<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Services\Gcore\GcoreAttackSyncService;
use Pterodactyl\Services\Gcore\GcoreClient;

class SyncGcoreAttackEventsCommand extends Command
{
    protected $signature = 'p:maintenance:sync-gcore-attacks
                            {--days=7 : How many days of event logs to pull}
                            {--purge-only : Only delete events older than retention}';

    protected $description = 'Sync Gcore DDoS attack event logs into the local database.';

    public function handle(): int
    {
        if ($this->option('purge-only')) {
            $service = app(GcoreAttackSyncService::class);
            $deleted = $service->purgeOlderThanDays();
            $this->line(sprintf('Gcore attack purge: deleted=%d', $deleted));

            return self::SUCCESS;
        }

        if (trim((string) config('gcore.api_key')) === '') {
            $this->warn('GCORE_API_KEY is empty; skipping attack sync.');

            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?: config('gcore.attack_sync_days', 7));
        $service = new GcoreAttackSyncService(GcoreClient::fromConfig());
        $stats = $service->sync($days, true);

        $this->line(sprintf(
            'Gcore attack sync: upserted=%d details=%d purged=%d errors=%d',
            $stats['upserted'],
            $stats['details'],
            $stats['purged'],
            $stats['errors']
        ));

        return $stats['errors'] > 0 && $stats['upserted'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
