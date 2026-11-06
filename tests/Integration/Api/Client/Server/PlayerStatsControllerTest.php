<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\ServerPlayerSample;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class PlayerStatsControllerTest extends ClientApiIntegrationTestCase
{
    public function testPlayerStatsEndpointReturnsSeriesAndSummary(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);

        Egg::query()->whereKey($server->egg_id)->update(['gamedig' => 'cs16']);

        ServerPlayerSample::query()->create([
            'server_id' => $server->id,
            'players' => 4,
            'max_players' => 32,
            'online' => true,
            'sampled_at' => now()->subHours(3)->minute(0)->second(0),
        ]);
        ServerPlayerSample::query()->create([
            'server_id' => $server->id,
            'players' => 10,
            'max_players' => 32,
            'online' => true,
            'sampled_at' => now()->subHours(2)->minute(0)->second(0),
        ]);
        ServerPlayerSample::query()->create([
            'server_id' => $server->id,
            'players' => 6,
            'max_players' => 32,
            'online' => true,
            'sampled_at' => now()->subHour()->minute(0)->second(0),
        ]);

        $response = $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/player-stats");

        $response->assertOk();
        $response->assertJsonPath('object', 'player_stats');
        $response->assertJsonPath('attributes.supported', true);
        $response->assertJsonPath('attributes.days', 7);
        $response->assertJsonPath('attributes.summary.current', 6);
        $response->assertJsonPath('attributes.summary.peak', 10);
        $response->assertJsonPath('attributes.summary.average', 6.67);
        $this->assertCount(3, $response->json('attributes.samples'));
        $this->assertNotNull($response->json('attributes.summary.busiest_hour'));
        $this->assertSame(10.0, (float) $response->json('attributes.summary.busiest_hour.average_players'));
    }

    public function testPlayerStatsEndpointReportsUnsupportedWithoutGamedig(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);

        Egg::query()->whereKey($server->egg_id)->update(['gamedig' => null]);

        $response = $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/player-stats");

        $response->assertOk();
        $response->assertJsonPath('attributes.supported', false);
        $response->assertJsonPath('attributes.samples', []);
        $response->assertJsonPath('attributes.summary.current', null);
    }
}
