<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\FastDlSyncService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\FastDl\SyncFastDlRequest;

class FastDlController extends ClientApiController
{
    /**
     * FastDlController constructor.
     */
    public function __construct(private FastDlSyncService $syncService)
    {
        parent::__construct();
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
