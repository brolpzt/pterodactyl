<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Illuminate\Http\Client\Request;
use Pterodactyl\Models\GcoreIpList;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Gcore\GcoreProfileCache;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class GcoreDdosControllerTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private User $admin;

    private array $acl = [];

    /** @var array<string, int> "METHOD path" => calls */
    private array $calls = [];

    public function setUp(): void
    {
        parent::setUp();

        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        config()->set('gcore.api_key', 'test-key');
        Cache::flush();

        $this->admin = User::factory()->create(['root_admin' => true]);
        $this->withoutMiddleware(\Pterodactyl\Http\Middleware\VerifyCsrfToken::class);

        $this->acl = [
            ['policy' => 'allowlist', 'sip_list' => ['187.94.104.229'], 'dport_list' => [], 'proto_list' => ['any'], 'sport_list' => []],
            ['policy' => 'udp-general', 'sip_list' => [], 'dport_list' => [27015], 'proto_list' => ['udp'], 'sport_list' => []],
        ];

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $key = $request->method() . ' ' . $path;
            $this->calls[$key] = ($this->calls[$key] ?? 0) + 1;

            if ($path === '/security/iaas/v2/profiles') {
                return Http::response([$this->profile()]);
            }
            if ($path === '/security/iaas/v2/profiles/77') {
                if ($request->method() === 'PUT') {
                    $this->acl = $request->data()['fields'][1]['field_value'];
                }

                return Http::response($this->profile());
            }

            return Http::response('unexpected', 500);
        });
    }

    private function profile(): array
    {
        return [
            'id' => 77,
            'ip_address' => '203.0.113.10',
            'site' => 'SP',
            'plan' => 'x',
            'profile_template' => ['id' => 9, 'name' => 'Game'],
            'status' => ['status' => 'Updated'],
            'fields' => [
                ['name' => 'Rate limiter low', 'base_field' => 1, 'field_value' => 50],
                ['name' => 'ACL list', 'base_field' => 3, 'field_value' => $this->acl],
            ],
        ];
    }

    private function gets(): int
    {
        return $this->calls['GET /security/iaas/v2/profiles/77'] ?? 0;
    }

    private function form(string $hash, string $udpPort): array
    {
        return [
            'form_hash' => $hash,
            'rate_low' => 50, 'rate_medium' => 150, 'rate_high' => 300, 'rate_geo' => 300,
            'acl' => [
                ['policy' => 'allowlist', 'proto_list' => 'any', 'dport_list' => '', 'sport_list' => '', 'sip_list' => '187.94.104.229'],
                ['policy' => 'udp-general', 'proto_list' => 'udp', 'dport_list' => $udpPort, 'sport_list' => '', 'sip_list' => ''],
            ],
        ];
    }

    public function testIndexRendersWithoutCallingGcoreAndRefreshesInBackground(): void
    {
        $this->actingAs($this->admin)->get('/admin/gcore')
            ->assertOk()
            ->assertSee('Carregando perfis da Gcore');
        $this->assertSame([], $this->calls);

        $live = $this->actingAs($this->admin)->getJson('/admin/gcore/live/profiles')->assertOk();
        $this->assertStringContainsString('203.0.113.10', $live->json('html'));

        // Now the list (and profile 77, seeded from it) come from the cache.
        $this->actingAs($this->admin)->get('/admin/gcore')->assertOk()->assertSee('203.0.113.10')->assertSee('Cópia de');
        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')->assertOk();
        $this->assertSame(0, $this->gets());
    }

    public function testEditorUsesCacheAndRefusesStaleSaves(): void
    {
        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')->assertOk();
        $this->assertSame(1, $this->gets());
        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')->assertOk();
        $this->assertSame(1, $this->gets(), 'second visit renders from the cache');
        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77?fresh=1')->assertOk();
        $this->assertSame(2, $this->gets());

        $hash = GcoreProfileCache::formHash($this->profile());
        $live = $this->actingAs($this->admin)->getJson('/admin/gcore/live/profiles/77')->assertOk();
        $this->assertSame($hash, $live->json('hash'));

        // Someone changes the profile on Gcore: a save built from the old page is refused.
        $this->acl[1]['dport_list'] = [27016];
        $this->actingAs($this->admin)->post('/admin/gcore/profiles/77', $this->form($hash, '27015,27017'))
            ->assertRedirect()
            ->assertSessionHas('alert_messages', fn ($a) => str_contains(implode(' ', $a['danger'] ?? []), 'mudou na Gcore'));
        $this->assertArrayNotHasKey('PUT /security/iaas/v2/profiles/77', $this->calls);
        $this->assertSame([27016], $this->acl[1]['dport_list']);

        // The refused save re-read the profile, so the cache (and the page) now has the current version.
        $current = GcoreProfileCache::formHash($this->profile());
        $gets = $this->gets();
        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')->assertOk()->assertSee('value="' . $current . '"', false);
        $this->assertSame($gets, $this->gets());

        // A save from the current page goes through and the cached copy follows the PUT.
        $this->actingAs($this->admin)->post('/admin/gcore/profiles/77', $this->form($current, '27016,27018'))
            ->assertRedirect()
            ->assertSessionHas('alert_messages', fn ($a) => str_contains(implode(' ', $a['success'] ?? []), 'Enviado à Gcore'));
        $this->assertSame([27016, 27018], $this->acl[1]['dport_list']);
        $gets = $this->gets();
        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')
            ->assertOk()
            ->assertSee('name="acl[1][dport_list]" value="27016,27018"', false)
            ->assertSee('value="' . GcoreProfileCache::formHash($this->profile()) . '"', false);
        $this->assertSame($gets, $this->gets());
    }

    public function testIpListEntriesAreServedSeparatelyWithEtag(): void
    {
        $list = new GcoreIpList(['name' => 'CF', 'url' => 'https://x.test/a', 'refresh_hours' => 24, 'enabled' => true]);
        $list->forceFill(['entries' => ['173.245.48.0/20'], 'entry_count' => 1])->save();

        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')
            ->assertOk()
            ->assertDontSee('173.245.48.0/20');

        $res = $this->actingAs($this->admin)->getJson('/admin/gcore/live/ip-list-entries')->assertOk();
        $this->assertSame(['173.245.48.0/20'], $res->json('lists.' . $list->id));
        $etag = $res->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->actingAs($this->admin)->getJson('/admin/gcore/live/ip-list-entries', ['If-None-Match' => $etag])->assertStatus(304);
    }
}
