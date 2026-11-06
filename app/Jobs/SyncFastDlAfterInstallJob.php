<?php

namespace Pterodactyl\Jobs;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Services\Servers\FastDlSyncService;

/**
 * Synchronizes FastDL after a successful install when the egg asks for it.
 * Wings has already saved the install manifest at this point, so stock game
 * files are skipped.
 */
class SyncFastDlAfterInstallJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $serverId)
    {
    }

    public static function shouldRun(Server $server): bool
    {
        $egg = $server->egg;

        return $server->fastdl_enabled
            && $egg !== null
            && $egg->fastdl_sync_on_install
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
            Log::warning('FastDL sync after install failed.', ['server_id' => $server->id, 'error' => $exception->getMessage()]);
        }
    }
}
