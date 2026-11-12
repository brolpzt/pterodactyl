<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BoostConnection;
use Pterodactyl\Services\Boost\ServerBoostService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class ServerBoostControllerTest extends ClientApiIntegrationTestCase
{
    protected function tearDown(): void
    {
        BoostConnection::query()->delete();
        $settings = app(SettingsRepositoryInterface::class);
        $settings->forget(ServerBoostService::SETTING_EGGS);
        $settings->forget(ServerBoostService::SETTING_CLIENT_TAB);

        parent::tearDown();
    }

    public function testOwnerSeesOnlyTheirServersBoostPlayers(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $service = app(ServerBoostService::class);
        $service->setEggIds([$server->egg_id]);
        $service->setClientTabEnabled(true);

        $service->record($server, ['player_name' => 'Fulano', 'steam_id' => 'STEAM_0:0:1', 'player_ip' => '200.1.2.3'], '10.0.0.1');
        $service->record($server, ['player_name' => 'Fulano', 'steam_id' => 'STEAM_0:0:1'], '10.0.0.1');
        $service->record($server, ['player_name' => 'Ciclano'], '10.0.0.1');
        BoostConnection::query()->create(['server_id' => $server->id, 'address' => 'x', 'player_name' => 'Antigo', 'created_at' => now()->subDays(10)]);
        $other = $this->createServerModel(['egg_id' => $server->egg_id]);
        $service->record($other, ['player_name' => 'Outro'], '10.0.0.1');

        $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}")
            ->assertOk()->assertJsonPath('attributes.boost_enabled', true);

        $response = $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/boost")->assertOk();
        $response->assertJsonPath('object', 'server_boost')
            ->assertJsonPath('attributes.period', '7d')
            ->assertJsonPath('attributes.listed', true)
            ->assertJsonPath('attributes.total', 3)
            ->assertJsonPath('attributes.players', 2);
        $this->assertSame(['Ciclano', 'Fulano', 'Fulano'], array_column($response->json('attributes.connections'), 'player'));
        $this->assertSame(3, array_sum(array_column($response->json('attributes.hourly'), 'count')));
        $this->assertStringNotContainsString('200.1.2.3', $response->getContent(), 'no player IPs for clients');

        $month = $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/boost?period=30d")->assertOk();
        $month->assertJsonPath('attributes.total', 4);

        // Switched to admins only: the owner loses the tab, a root admin keeps it.
        $service->setClientTabEnabled(false);
        $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}")
            ->assertOk()->assertJsonPath('attributes.boost_enabled', false);
        $admin = User::factory()->create(['root_admin' => true]);
        $this->actingAs($admin)->getJson("/api/client/servers/{$server->uuid}/boost")->assertOk();
        $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/boost")->assertNotFound();
    }

    public function testServersOutsideTheBoostEggsHaveNoTab(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $other = Egg::query()->where('id', '!=', $server->egg_id)->firstOrFail();
        app(ServerBoostService::class)->setEggIds([$other->id]);
        app(ServerBoostService::class)->setClientTabEnabled(true);

        $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}")
            ->assertOk()->assertJsonPath('attributes.boost_enabled', false);
        $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/boost")->assertNotFound();
    }
}
