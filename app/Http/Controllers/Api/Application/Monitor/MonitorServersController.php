<?php

namespace Pterodactyl\Http\Controllers\Api\Application\Monitor;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Support\MonitorProtocolResolver;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Pterodactyl\Http\Requests\Api\Application\Monitor\GetMonitorServersRequest;

class MonitorServersController extends ApplicationApiController
{
    /**
     * Lista servidores no formato JSON consumido pelo programa de monitorização.
     */
    public function index(GetMonitorServersRequest $request): JsonResponse
    {
        $servers = Server::query()
            ->with(['egg', 'allocation', 'node'])
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', Server::STATUS_SUSPENDED);
            })
            ->whereNotNull('allocation_id')
            ->orderBy('id')
            ->get();

        $entries = [];
        foreach ($servers as $server) {
            $entry = MonitorProtocolResolver::toMonitorEntry($server);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return response()->json(['servers' => $entries]);
    }
}
