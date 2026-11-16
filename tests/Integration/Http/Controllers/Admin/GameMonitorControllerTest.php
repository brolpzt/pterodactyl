<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Mockery as m;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\GameQuery\GameQueryService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pterodactyl\Exceptions\Service\GameQuery\GameQueryException;

class GameMonitorControllerTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        $this->admin = User::factory()->create(['root_admin' => true]);
    }

    private function cs16Server(array $attributes = []): Server
    {
        $egg = Egg::query()->firstOrFail();
        $egg->forceFill(['gamedig' => 'cs16'])->save();

        return $this->createServerModel(array_merge(['egg_id' => $egg->id, 'name' => 'Meu CS'], $attributes));
    }

    public function testIndexListsQueryableServers(): void
    {
        $server = $this->cs16Server();

        $this->actingAs($this->admin)->get('/admin/monitor')->assertOk()
            ->assertSee('Game Monitor')
            ->assertSee('Meu CS')
            ->assertSee('"type":"cs16"', false)
            ->assertSee(json_encode(route('admin.monitor.query', $server->id)), false);

        $this->actingAs(User::factory()->create())->get('/admin/monitor')->assertForbidden();
    }

    public function testQueryReturnsAndCachesTheResult(): void
    {
        $server = $this->cs16Server();
        $service = m::mock(GameQueryService::class);
        $service->expects('query')->once()->andReturn([
            'online' => true, 'hostname' => 'Servidor ^1Top', 'map' => 'de_dust2', 'players' => 2, 'max_players' => 32,
            'player_list' => [['name' => 'Fulano', 'score' => 3, 'time' => 60]],
        ]);
        $this->app->instance(GameQueryService::class, $service);

        $this->actingAs($this->admin)->getJson("/admin/monitor/servers/{$server->id}")->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonPath('map', 'de_dust2')
            ->assertJsonPath('player_list.0.name', 'Fulano')
            ->assertJsonStructure(['took_ms']);
        // Second call inside the cache window does not query again.
        $this->actingAs($this->admin)->getJson("/admin/monitor/servers/{$server->id}")->assertOk()->assertJsonPath('players', 2);
    }

    public function testQueryErrorsAndSuspendedServers(): void
    {
        $server = $this->cs16Server();
        $service = m::mock(GameQueryService::class);
        $service->expects('query')->once()->andThrow(new GameQueryException('Could not establish a connection'));
        $this->app->instance(GameQueryService::class, $service);

        $this->actingAs($this->admin)->getJson("/admin/monitor/servers/{$server->id}")->assertOk()
            ->assertJsonPath('online', false)
            ->assertJsonPath('error', 'Could not establish a connection');

        $suspended = $this->cs16Server(['status' => Server::STATUS_SUSPENDED]);
        $this->actingAs($this->admin)->getJson("/admin/monitor/servers/{$suspended->id}")->assertOk()
            ->assertJsonPath('error', 'Servidor suspenso.');
    }
}
