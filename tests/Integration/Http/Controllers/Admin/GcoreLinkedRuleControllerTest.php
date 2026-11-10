<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\GcoreLinkedRule;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class GcoreLinkedRuleControllerTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        config()->set('gcore.api_key', 'test-key');
        Cache::flush();

        $this->admin = User::factory()->create(['root_admin' => true]);
        $this->withoutMiddleware(\Pterodactyl\Http\Middleware\VerifyCsrfToken::class);

        Http::fake(function (Request $request) {
            return Http::response([
                'id' => 77,
                'ip_address' => '203.0.113.10',
                'profile_template' => ['id' => 9, 'name' => 'Game'],
                'status' => ['status' => 'Updated'],
                'fields' => [
                    ['name' => 'ACL list', 'base_field' => 3, 'field_value' => [
                        ['policy' => 'allowlist', 'sip_list' => ['216.238.125.59/32'], 'dport_list' => [27015], 'proto_list' => ['udp'], 'sport_list' => []],
                        ['policy' => 'ratelimiter-low', 'sip_list' => [], 'dport_list' => [], 'proto_list' => ['udp'], 'sport_list' => [27015, 27016]],
                        ['policy' => 'DROP', 'sip_list' => [], 'dport_list' => [27015], 'proto_list' => ['udp'], 'sport_list' => [27015]],
                        ['policy' => 'counter-strike-16-gold', 'sip_list' => [], 'dport_list' => [27015], 'proto_list' => ['udp'], 'sport_list' => []],
                    ]],
                ],
            ]);
        });
    }

    public function testCreateLinkInEggAndSeeItInTheEditor(): void
    {
        $this->actingAs($this->admin)->post('/admin/gcore/linked-rules', [
            'name' => 'CS 1.6 BOOST', 'policy' => 'allowlist', 'proto' => 'udp', 'sip_list' => '216.238.125.59', 'mode' => 'dport',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/gcore/linked-rules', [
            'name' => 'Origem = destino', 'policy' => 'DROP', 'proto' => 'udp', 'sip_list' => '', 'mode' => 'same_port',
        ])->assertRedirect();

        $boost = GcoreLinkedRule::query()->where('name', 'CS 1.6 BOOST')->firstOrFail();
        $same = GcoreLinkedRule::query()->where('name', 'Origem = destino')->firstOrFail();
        $this->assertSame(['216.238.125.59'], $boost->sipList());

        // Not used by any egg yet: the editor shows no badge (the sync ignores it).
        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')->assertOk()->assertDontSee('🔗', false);

        $egg = Egg::query()->firstOrFail();
        $slots = app(PortSlotSyncService::class)->normalizeSlots([
            ['env_variable' => 'SERVER_PORT', 'gcore_policy' => 'counter-strike-16-gold', 'gcore_proto' => 'udp', 'gcore_links' => [(string) $boost->id, $same->id, $same->id]],
        ]);
        $this->assertSame([$boost->id, $same->id], $slots[0]['gcore_links']);
        $egg->forceFill(['port_slots' => $slots])->save();

        $this->actingAs($this->admin)->get('/admin/gcore/linked-rules')
            ->assertOk()
            ->assertSee($egg->name . ' (SERVER_PORT)');

        $page = $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')->assertOk();
        $page->assertSee('🔗 CS 1.6 BOOST', false)->assertSee('🔗 Origem = destino', false);
        $page->assertSee('data-linked="CS 1.6 BOOST"', false);

        // In use: cannot be deleted.
        $this->actingAs($this->admin)->delete("/admin/gcore/linked-rules/{$boost->id}")->assertRedirect();
        $this->assertNotNull($boost->fresh());

        // Last: the error handler rolls back the test transaction.
        $this->actingAs($this->admin)->post('/admin/gcore/linked-rules', [
            'name' => 'Ruim', 'policy' => 'DROP', 'proto' => 'udp', 'sip_list' => '1.2.3', 'mode' => 'dport',
        ])->assertStatus(422)->assertJsonPath('errors.0.meta.source_field', 'sip_list');
        $this->assertNull(GcoreLinkedRule::query()->where('name', 'Ruim')->first());
    }
}
