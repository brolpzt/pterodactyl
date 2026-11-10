<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreIpListBinding;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class GcoreIpListControllerTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private User $admin;

    /** ACL of the fake Gcore profile 77. */
    private array $acl = [];

    public function setUp(): void
    {
        parent::setUp();

        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        config()->set('gcore.api_key', 'test-key');

        $this->admin = User::factory()->create(['root_admin' => true]);
        $this->withoutMiddleware(\Pterodactyl\Http\Middleware\VerifyCsrfToken::class);

        $this->acl = [
            ['policy' => 'allowlist', 'sip_list' => ['187.94.104.229'], 'dport_list' => [], 'proto_list' => ['any'], 'sport_list' => []],
            ['policy' => 'udp-general', 'sip_list' => [], 'dport_list' => [27015], 'proto_list' => ['udp'], 'sport_list' => []],
        ];

        Http::fake(function (Request $request) {
            if (str_starts_with($request->url(), 'https://lists.example.test/')) {
                return Http::response("173.245.48.0/20\n103.21.244.0/22\n2400:cb00::/32\n");
            }
            if (str_contains($request->url(), '/security/iaas/v2/profiles/77')) {
                if ($request->method() === 'PUT') {
                    $this->acl = $request->data()['fields'][1]['field_value'];
                }

                return Http::response([
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
                ]);
            }

            return Http::response('unexpected', 500);
        });
    }

    public function testCreateViewAndUseListInProfileEditor(): void
    {
        $this->actingAs($this->admin)->post('/admin/gcore/ip-lists', [
            'name' => 'Cloudflare <x>',
            'url' => 'https://lists.example.test/cf.txt',
            'refresh_hours' => 12,
            'enabled' => '1',
        ])->assertRedirect();

        $list = GcoreIpList::query()->where('name', 'Cloudflare <x>')->firstOrFail();
        $this->assertSame(['103.21.244.0/22', '173.245.48.0/20'], $list->entryList());

        $this->actingAs($this->admin)->get('/admin/gcore/ip-lists')
            ->assertOk()
            ->assertSee('Cloudflare &lt;x&gt;', false)
            ->assertSee('1 IPv6');
        $this->actingAs($this->admin)->get("/admin/gcore/ip-lists/{$list->id}")
            ->assertOk()
            ->assertSee('173.245.48.0/20');

        // Bind the list to the allowlist rule via the editor form.
        $this->actingAs($this->admin)->post('/admin/gcore/profiles/77', [
            'rate_low' => 50, 'rate_medium' => 150, 'rate_high' => 300, 'rate_geo' => 300,
            'acl' => [
                ['policy' => 'allowlist', 'proto_list' => 'any', 'dport_list' => '', 'sport_list' => '', 'sip_list' => '187.94.104.229', 'ip_lists' => (string) $list->id],
                ['policy' => 'udp-general', 'proto_list' => 'udp', 'dport_list' => '27015', 'sport_list' => '', 'sip_list' => ''],
            ],
        ])->assertRedirect();

        $this->assertSame(['187.94.104.229', '103.21.244.0/22', '173.245.48.0/20'], $this->acl[0]['sip_list']);
        $this->assertArrayNotHasKey('ip_lists', $this->acl[0]);
        $this->assertSame(1, GcoreIpListBinding::query()->count());

        // Editor shows the binding (hidden input carries the list id, sip only the manual IP).
        $this->actingAs($this->admin)->get('/admin/gcore/profiles/77')
            ->assertOk()
            ->assertSee('name="acl[0][ip_lists]" value="' . $list->id . '"', false)
            ->assertSee('name="acl[0][sip_list]" value="187.94.104.229"', false);

        // A list in use cannot be deleted.
        $this->actingAs($this->admin)->delete("/admin/gcore/ip-lists/{$list->id}")->assertRedirect();
        $this->assertNotNull($list->fresh());
    }
}
