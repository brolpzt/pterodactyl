<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\BoostConnection;
use Pterodactyl\Services\Boost\ServerBoostService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ServerBoostControllerTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private User $admin;
    private ServerBoostService $service;
    private string $token;
    private Egg $cs;

    public function setUp(): void
    {
        parent::setUp();

        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        $this->admin = User::factory()->create(['root_admin' => true]);
        $this->withoutMiddleware(\Pterodactyl\Http\Middleware\VerifyCsrfToken::class);

        $this->service = app(ServerBoostService::class);
        $this->token = $this->service->regenerateToken();
        $this->cs = Egg::query()->firstOrFail();
        $this->service->setEggIds([$this->cs->id]);
    }

    private function csServer(int $port, array $attributes = []): Server
    {
        $server = $this->createServerModel(array_merge(['egg_id' => $this->cs->id], $attributes));
        $server->allocation->forceFill(['ip' => '89.39.161.172', 'ip_alias' => null, 'port' => $port])->save();

        return $server->fresh(['allocation', 'node']);
    }

    public function testServerListOnlyHasVisibleServersOfTheBoostEggs(): void
    {
        $a = $this->csServer(27015);
        $hidden = $this->csServer(27016);
        Server::query()->whereKey($hidden->id)->update(['boost_hidden' => true]);
        $this->csServer(27017, ['status' => Server::STATUS_SUSPENDED]);
        $other = Egg::query()->where('id', '!=', $this->cs->id)->firstOrFail();
        $this->createServerModel(['egg_id' => $other->id]);

        $this->getJson("/api/boost/{$this->token}/servers")
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('servers.0.id', $a->id)
            ->assertJsonPath('servers.0.address', '89.39.161.172:27015')
            ->assertJsonPath('servers.0.port', 27015);

        $txt = $this->get("/api/boost/{$this->token}/servers?format=txt")->assertOk();
        $this->assertSame("89.39.161.172:27015\n", $txt->getContent());

        $this->getJson('/api/boost/wrong-token/servers')->assertNotFound();
    }

    public function testConnectRecordsThePlayerByIdOrAddress(): void
    {
        $server = $this->csServer(27015);
        Allocation::query()->whereKey($server->allocation_id)->update(['ip_alias' => 'cs.example.com']);

        $this->postJson("/api/boost/{$this->token}/connect", [
            'server_id' => $server->id, 'player' => "Jog\x01ador", 'player_ip' => '200.1.2.3', 'steam_id' => 'STEAM_0:1:123',
        ])->assertCreated()->assertJsonPath('server_id', $server->id)->assertJsonPath('address', 'cs.example.com:27015');

        // By the real IP or the alias; GET works too.
        $this->get("/api/boost/{$this->token}/connect?server=89.39.161.172:27015&player=Outro")->assertCreated();
        $this->post("/api/boost/{$this->token}/connect", ['server' => 'CS.example.com:27015'])->assertCreated();

        $rows = BoostConnection::query()->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertSame('Jogador', $rows[0]->player_name);
        $this->assertSame('200.1.2.3', $rows[0]->player_ip);
        $this->assertSame('STEAM_0:1:123', $rows[0]->steam_id);
        $this->assertSame($server->id, $rows[1]->server_id);

        $this->postJson("/api/boost/{$this->token}/connect", ['server' => '89.39.161.172:27999'])->assertNotFound();
        $this->postJson("/api/boost/{$this->token}/connect", ['player' => 'x'])->assertStatus(422);
        $this->postJson("/api/boost/{$this->token}/connect", ['server_id' => $server->id, 'player_ip' => 'nope'])->assertStatus(422);
        $this->assertSame(3, BoostConnection::query()->count());

        // Last: abort(404) makes the error handler roll back the test transaction.
        $this->postJson('/api/boost/wrong/connect', ['server_id' => $server->id])->assertNotFound();
    }

    public function testAdminPageChartsAndToggle(): void
    {
        $server = $this->csServer(27015);
        $this->service->record($server, ['player_name' => 'Fulano', 'steam_id' => 'STEAM_0:0:1'], '10.0.0.1');
        $this->service->record($server, ['player_name' => 'Fulano', 'steam_id' => 'STEAM_0:0:1'], '10.0.0.1');
        $this->service->record($server, ['player_name' => 'Ciclano'], '10.0.0.1');
        BoostConnection::query()->create(['server_id' => $server->id, 'address' => 'x', 'created_at' => now()->subDays(3)]);

        $day = $this->service->stats('24h');
        $this->assertSame(3, $day['total']);
        $this->assertSame(2, $day['players']);
        $this->assertCount(24, $day['series']);
        $this->assertSame(3, $day['series'][23]['count']);
        $this->assertSame(3, $day['top'][0]['count']);

        $week = $this->service->stats('7d');
        $this->assertSame(4, $week['total']);
        $this->assertCount(7, $week['series']);
        $this->assertSame(4, array_sum(array_column($week['series'], 'count')));

        $this->actingAs($this->admin)->get('/admin/boost')
            ->assertOk()
            ->assertSee('Jogadores conectados via boost')
            ->assertSee('Fulano')
            ->assertSee("/api/boost/{$this->token}/connect", false);

        $this->actingAs($this->admin)->getJson('/admin/boost/live?q=Ciclano&range=7d')
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'Ciclano') && !str_contains($html, 'Fulano'));

        $this->actingAs($this->admin)->get("/admin/servers/view/{$server->id}/manage")
            ->assertOk()->assertSee('Hide from boost list');

        $this->actingAs($this->admin)->post("/admin/boost/servers/{$server->id}", ['hidden' => 1, 'back' => 'manage'])
            ->assertRedirect("/admin/servers/view/{$server->id}/manage");
        $this->assertTrue((bool) $server->fresh()->boost_hidden);
        $this->getJson("/api/boost/{$this->token}/servers")->assertJsonPath('count', 0);

        BoostConnection::query()->create(['server_id' => $server->id, 'address' => 'x', 'created_at' => CarbonImmutable::now()->subDays(100)]);
        $this->assertSame(1, $this->service->purge());

        // Last: abort(404) makes the error handler roll back the test transaction.
        $old = $this->token;
        $this->actingAs($this->admin)->post('/admin/boost/token')->assertRedirect('/admin/boost');
        $this->getJson("/api/boost/{$old}/servers")->assertNotFound();
    }
}
