<?php

namespace Pterodactyl\Http\Controllers\Api\Remote\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Jobs\AutoSyncFastDlJob;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Eloquent\ServerRepository;
use Pterodactyl\Exceptions\Http\HttpForbiddenException;

class ServerFastDlController extends Controller
{
    public function __construct(private ServerRepository $repository)
    {
    }

    /**
     * Wings detected new or changed FastDL files: queue a synchronization.
     * auto_sync=false tells Wings to stop watching this server.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function changed(Request $request, string $uuid): JsonResponse
    {
        $server = $this->repository->getByUuid($uuid);

        if (!$server->node->is($request->attributes->get('node'))) {
            throw new HttpForbiddenException('Requesting node does not have permission to access this server.');
        }

        $server->loadMissing('egg');
        $enabled = AutoSyncFastDlJob::shouldRun($server);
        if ($enabled) {
            AutoSyncFastDlJob::dispatch($server->id);
        }

        // A suspended or installing server keeps its watcher; it syncs later.
        $keepWatching = $enabled || ($server->fastdl_enabled && $server->status !== null && $server->egg?->fastdl_auto_sync);

        return new JsonResponse(['auto_sync' => (bool) $keepWatching]);
    }
}
