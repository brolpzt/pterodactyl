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
     * Returns historical player-count samples for the server.
     *
     * Query: period=15m|1h|6h|24h|7d (default 7d). Legacy ?days=N still accepted.
     */
    public function __invoke(GetServerRequest $request, Server $server): array
    {
        $period = $request->query('period');
        if (!is_string($period) || $period === '') {
            $days = (int) $request->query('days', 7);
            $period = match (true) {
                $days <= 0 => '7d',
                $days === 1 => '24h',
                default => '7d',
            };
        }

        return [
            'object' => 'player_stats',
            'attributes' => $this->playerStatsService->forServer($server, $period),
        ];
    }
}
