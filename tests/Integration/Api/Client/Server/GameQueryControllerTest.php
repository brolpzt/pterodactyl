<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Permission;
use Pterodactyl\Services\GameQuery\GameQueryService;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class GameQueryControllerTest extends ClientApiIntegrationTestCase
{
    public function testGameQueryIsReturnedForServerWithGamedigConfigured()
    {
        $service = \Mockery::mock(GameQueryService::class);
        $this->app->instance(GameQueryService::class, $service);

        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);

        $egg = Egg::query()->findOrFail($server->egg_id);
        $egg->forceFill(['gamedig' => 'cs16'])->save();

        $service->expects('query')->once()->with(\Mockery::on(function ($value) use ($server) {
            return $server->uuid === $value->uuid;
        }))->andReturn([
            'online' => true,
            'type' => 'cs16',
            'address' => '127.0.0.1',
            'port' => 27015,
            'hostname' => 'HostGamer CS',
            'map' => 'de_dust2',
            'game' => 'cstrike',
            'players' => 2,
            'max_players' => 32,
            'password_protected' => false,
            'version' => '1.1.2.7/Stdio',
            'player_list' => [
                ['name' => 'Player1', 'score' => 10, 'time' => 120],
            ],
        ]);

        $response = $this->actingAs($user)->getJson("/api/client/servers/$server->uuid/query");

        $response->assertOk();
        $response->assertJson([
            'object' => 'game_query',
            'attributes' => [
                'online' => true,
                'type' => 'cs16',
                'hostname' => 'HostGamer CS',
                'map' => 'de_dust2',
                'players' => 2,
                'max_players' => 32,
            ],
        ]);
    }
}
