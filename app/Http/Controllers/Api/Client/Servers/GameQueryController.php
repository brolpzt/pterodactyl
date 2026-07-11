<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Carbon\Carbon;
use Illuminate\Cache\Repository;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\GameQuery\GameQueryService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;

class GameQueryController extends ClientApiController
{
    public function __construct(
        private Repository $cache,
        private GameQueryService $gameQueryService,
    ) {
        parent::__construct();
    }

    /**
     * Consulta o servidor de jogo via GameQ, usando o tipo definido no campo gamedig do egg.
     */
    public function __invoke(GetServerRequest $request, Server $server): array
    {
        $key = sprintf('game-query:%s', $server->uuid);

        $attributes = $this->cache->remember($key, Carbon::now()->addSeconds(60), function () use ($server) {
            return $this->gameQueryService->query($server);
        });

        return [
            'object' => 'game_query',
            'attributes' => $attributes,
        ];
    }
}
