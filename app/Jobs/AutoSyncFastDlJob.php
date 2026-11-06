<?php

namespace Pterodactyl\Jobs;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Pterodactyl\Services\Servers\FastDlSyncService;

/**
 * Synchronizes FastDL when Wings reports new or changed files of the egg's
 * FastDL types (automatic synchronization).
 */
class AutoSyncFastDlJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 60;

    public function __construct(public int $serverId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->serverId;
    }

    public static function shouldRun(Server $server): bool
    {
        $egg = $server->egg;

        return $server->fastdl_enabled
            && $server->status === null
            && $egg !== null
            && $egg->fastdl_auto_sync
            && in_array('fastdl', $egg->features ?? [], true);
    }

    public function handle(FastDlSyncService $syncService): void
    {
        $server = Server::query()->with(['egg', 'node'])->find($this->serverId);
        if (!$server || !self::shouldRun($server)) {
            return;
        }

        try {
            $syncService->handle($server);
        } catch (\Throwable $exception) {
            Log::warning('FastDL automatic sync failed.', ['server_id' => $server->id, 'error' => $exception->getMessage()]);
        }
    }
}
