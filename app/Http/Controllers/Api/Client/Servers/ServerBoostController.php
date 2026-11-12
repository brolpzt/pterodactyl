<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Boost\ServerBoostService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServerBoostController extends ClientApiController
{
    public function __construct(private ServerBoostService $service)
    {
        parent::__construct();
    }

    /**
     * Players the boost system sent to this server. Query: period=24h|7d|30d (default 7d).
     */
    public function __invoke(GetServerRequest $request, Server $server): array
    {
        if (!$this->service->visibleTo($server, $request->user())) {
            throw new NotFoundHttpException();
        }

        return [
            'object' => 'server_boost',
            'attributes' => $this->service->forServer($server, (string) $request->query('period', '7d')),
        ];
    }
}
