<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\FastDlSyncService;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\FastDl\SyncFastDlRequest;

class FastDlController extends ClientApiController
{
    /**
     * FastDlController constructor.
     */
    public function __construct(
        private FastDlSyncService $syncService,
        private DaemonServerRepository $daemonServerRepository,
    ) {
        parent::__construct();
    }

    /**
     * FastDL sync progress (or last result) and whether original files are mapped.
     * Null parts mean Wings could not be reached or does not support it yet.
     */
    public function status(GetServerRequest $request, Server $server): array
    {
        if (!$server->fastdl_enabled) {
            abort(404, 'FastDL is not enabled for this server.');
        }

        $repository = $this->daemonServerRepository->setServer($server);

        try {
            $sync = $repository->getFastDlSyncStatus();
        } catch (\Throwable) {
            $sync = null;
        }

        try {
            $manifest = $repository->getFastDlManifest();
            $manifest = ['exists' => (bool) ($manifest['exists'] ?? false)];
        } catch (\Throwable) {
            $manifest = null;
        }

        return [
            'object' => 'fastdl_status',
            'attributes' => ['sync' => $sync, 'manifest' => $manifest],
        ];
    }

    /**
     * Triggers a FastDL synchronization for the server.
     */
    public function sync(SyncFastDlRequest $request, Server $server): array
    {
        if (!$server->fastdl_enabled) {
            abort(404, 'FastDL is not enabled for this server.');
        }

        $this->syncService->handle($server);

        return [
            'success' => true,
        ];
    }
}
