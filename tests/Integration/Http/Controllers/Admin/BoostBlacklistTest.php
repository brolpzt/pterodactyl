<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\BoostBlacklistEntry;
use Pterodactyl\Services\Boost\ServerBoostService;
use Pterodactyl\Services\Boost\BoostBlacklistService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class BoostBlacklistTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private User $admin;
    private string $token;

    public function setUp(): void
    {
        parent::setUp();

        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        $this->admin = User::factory()->create(['root_admin' => true]);
        $this->withoutMiddleware(\Pterodactyl\Http\Middleware\VerifyCsrfToken::class);
        $this->token = app(ServerBoostService::class)->regenerateToken();
    }

    public function testNormalizeAndMatch(): void
    {
        $this->assertSame('177.10.0.5', BoostBlacklistService::normalize('ip', ' 177.10.0.5 '));
        $this->assertSame('177.10.0.0/16', BoostBlacklistService::normalize('ip', '177.10.0.0/16'));
        $this->assertSame('177.10.0.5', BoostBlacklistService::normalize('ip', '177.10.0.5/32'));
        $this->assertSame('2001:db8::1', BoostBlacklistService::normalize('ip', '2001:DB8:0::1'));
        $this->assertSame('STEAM_0:1:123', BoostBlacklistService::normalize('steam_id', 'steam_0:1:123'));
        $this->assertSame('Fulano de Tal', BoostBlacklistService::normalize('name', "  Fulano \t de\x01 Tal "));

        $service = app(BoostBlacklistService::class);
        $service->add('ip', '177.10.0.0/16', 'flood', null, $this->admin->id);
        $service->add('steam_id', 'STEAM_0:1:123', null, null, null);
        $service->add('name', 'Fulano', null, null, null);
        $service->add('name', 'fulano', 'atualizado', 7, null); // same name: updates, no duplicate
        $active = $service->active();

        $this->assertCount(3, $active);
        $this->assertSame('ip', BoostBlacklistService::match($active, '177.10.200.3', null, null)?->type);
        $this->assertNull(BoostBlacklistService::match($active, '177.11.0.1', null, null));
        $this->assertSame('steam_id', BoostBlacklistService::match($active, null, 'steam_0:1:123', null)?->type);
        $this->assertSame('name', BoostBlacklistService::match($active, null, null, 'FULANO')?->type);
        $this->assertSame('atualizado', BoostBlacklistEntry::query()->where('type', 'name')->sole()->reason);
        $this->assertNotNull(BoostBlacklistEntry::query()->where('type', 'name')->sole()->expires_at);

        // Expired entries leave the active list.
        BoostBlacklistEntry::query()->where('type', 'name')->update(['expires_at' => now()->subMinute()]);
        $this->assertCount(2, $service->active());
    }

    public function testApiListsActiveEntriesWithEtag(): void
    {
        $service = app(BoostBlacklistService::class);
        $service->add('ip', '177.10.0.5', null, null, null);
        $service->add('steam_id', 'STEAM_0:1:123', 'cheat', null, null);
        $old = $service->add('name', 'Velho', null, null, null);
        $old->forceFill(['expires_at' => now()->subDay()])->save();

        $res = $this->getJson("/api/boost/{$this->token}/blacklist")->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('ips', ['177.10.0.5'])
            ->assertJsonPath('steam_ids', ['STEAM_0:1:123'])
            ->assertJsonPath('names', [])
            ->assertJsonPath('entries.1.reason', 'cheat');
        $etag = $res->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->getJson("/api/boost/{$this->token}/blacklist", ['If-None-Match' => $etag])->assertStatus(304);
        $service->add('ip', '10.0.0.0/8', null, null, null);
        $this->getJson("/api/boost/{$this->token}/blacklist", ['If-None-Match' => $etag])->assertOk()->assertJsonPath('count', 3);

        $txt = $this->get("/api/boost/{$this->token}/blacklist?format=txt&type=ip")->assertOk();
        $this->assertSame("177.10.0.5\n10.0.0.0/8\n", $txt->getContent());
        $this->getJson("/api/boost/{$this->token}/blacklist?format=txt")->assertStatus(422);

        // connect tells the boost system the player is blacklisted.
        $egg = Egg::query()->firstOrFail();
        app(ServerBoostService::class)->setEggIds([$egg->id]);
        $server = $this->createServerModel(['egg_id' => $egg->id]);
        $this->postJson("/api/boost/{$this->token}/connect", ['server_id' => $server->id, 'steam_id' => 'steam_0:1:123'])
            ->assertCreated()->assertJsonPath('blacklisted', true);
        $this->postJson("/api/boost/{$this->token}/connect", ['server_id' => $server->id, 'steam_id' => 'STEAM_0:0:9'])
            ->assertCreated()->assertJsonPath('blacklisted', false);

        // Last: abort(404) makes the error handler roll back the test transaction.
        $this->getJson('/api/boost/wrong/blacklist')->assertNotFound();
    }

    public function testAdminAddsFromDashboardAndManually(): void
    {
        $egg = Egg::query()->firstOrFail();
        $boost = app(ServerBoostService::class);
        $boost->setEggIds([$egg->id]);
        $server = $this->createServerModel(['egg_id' => $egg->id]);
        $boost->record($server, ['player_name' => 'Trapaceiro', 'steam_id' => 'STEAM_0:0:77', 'player_ip' => '200.1.2.3'], null);

        $this->actingAs($this->admin)->get('/admin/boost')->assertOk()
            ->assertSee('por SteamID')->assertSee('por IP')->assertDontSee('>blacklist</span>', false);

        $back = route('admin.boost', ['range' => '7d']);
        $this->actingAs($this->admin)->post('/admin/boost/blacklist', ['type' => 'steam_id', 'value' => 'STEAM_0:0:77', 'back' => $back])
            ->assertRedirect($back);
        $this->actingAs($this->admin)->post('/admin/boost/blacklist', ['type' => 'ip', 'value' => '45.0.0.0/8', 'reason' => 'VPN', 'days' => 30, 'back' => 'https://evil.example'])
            ->assertRedirect('/admin/boost/blacklist');

        $this->actingAs($this->admin)->get('/admin/boost')->assertOk()->assertSee('>blacklist</span>', false);
        $page = $this->actingAs($this->admin)->get('/admin/boost/blacklist')->assertOk();
        $page->assertSee('STEAM_0:0:77')->assertSee('45.0.0.0/8')->assertSee('VPN')->assertSee($this->admin->username);

        $entry = BoostBlacklistEntry::query()->where('type', 'steam_id')->sole();
        $this->assertSame($this->admin->id, $entry->created_by);
        $this->actingAs($this->admin)->delete("/admin/boost/blacklist/{$entry->id}")->assertRedirect('/admin/boost/blacklist');
        $this->assertSame(1, BoostBlacklistEntry::query()->count());

        // Last: the validation error handler rolls back the test transaction.
        $this->actingAs($this->admin)->post('/admin/boost/blacklist', ['type' => 'ip', 'value' => '1.2.3'])
            ->assertStatus(422);
    }
}
