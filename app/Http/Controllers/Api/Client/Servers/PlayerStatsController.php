<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\PlayerStatsService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;

class PlayerStatsController extends ClientApiController
{
    public function __construct(private PlayerStatsService $playerStatsService)
    {
        parent::__construct();
    }

    /**
     * Returns historical player-count samples for the server (up to 7 days).
     */
    public function __invoke(GetServerRequest $request, Server $server): array
    {
        $days = (int) $request->query('days', 7);

        return [
            'object' => 'player_stats',
            'attributes' => $this->playerStatsService->forServer($server, $days),
        ];
    }
}
