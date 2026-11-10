<?php

namespace Pterodactyl\Tests\Integration\Services\Gcore;

use Illuminate\Http\Client\Request;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreIpListBinding;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListBindingService;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListRefreshService;

class GcoreIpListRefreshServiceTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private const LIST_URL = 'https://lists.example.test/ips.txt';
    private const PROFILE = 77;

    /** Body served for LIST_URL. */
    private string $listBody = '';
    private int $listStatus = 200;

    /** Gcore-side ACL of profile 77; PUT replaces it. */
    private array $acl = [];
    private int $puts = 0;
    private bool $gcoreDown = false;

    private GcoreIpListBindingService $bindings;
    private GcoreIpListRefreshService $refresh;

    public function setUp(): void
    {
        parent::setUp();

        Http::fake(function (Request $request) {
            if ($request->url() === self::LIST_URL) {
                return Http::response($this->listBody, $this->listStatus);
            }
            if (str_contains($request->url(), '/security/iaas/v2/profiles/' . self::PROFILE)) {
                if ($this->gcoreDown) {
                    return Http::response(['detail' => 'down'], 502);
                }
                if ($request->method() === 'PUT') {
                    ++$this->puts;
                    foreach ($request->data()['fields'] as $field) {
                        if ($field['base_field'] === 3) {
                            $this->acl = $field['field_value'];
                        }
                    }

                    return Http::response($this->profile() + ['status' => ['status' => 'Pending Update']]);
                }

                return Http::response($this->profile());
            }

            return Http::response('unexpected ' . $request->url(), 500);
        });

        $this->bindings = new GcoreIpListBindingService(new GcoreClient('test-key'));
        $this->refresh = new GcoreIpListRefreshService($this->bindings);
    }

    private function profile(): array
    {
        return [
            'id' => self::PROFILE,
            'ip_address' => '203.0.113.10',
            'site' => 'SP',
            'profile_template' => ['id' => 9],
            'status' => ['status' => 'Updated'],
            'fields' => [
                ['name' => 'Rate limiter low', 'base_field' => 1, 'field_value' => 50],
                ['name' => 'GEOIP list', 'base_field' => 2, 'field_value' => ['CN']],
                ['name' => 'ACL list', 'base_field' => 3, 'field_value' => $this->acl],
            ],
        ];
    }

    private function rule(string $policy, array $sip, array $dports = [], array $proto = ['udp']): array
    {
        return ['policy' => $policy, 'sip_list' => $sip, 'dport_list' => $dports, 'proto_list' => $proto, 'sport_list' => []];
    }

    /** Create a list, download it, and bind it (plus a manual IP) to an allowlist rule as the editor would. */
    private function boundList(string $body): GcoreIpList
    {
        $this->listBody = $body;
        $list = GcoreIpList::query()->create(['name' => 'Valve', 'url' => self::LIST_URL, 'refresh_hours' => 24, 'enabled' => true]);
        $this->assertSame('changed', $this->refresh->refresh($list)->status);

        $prepared = $this->bindings->prepareForSave([
            $this->rule('allowlist', ['187.94.104.229'], [27015]) + ['ip_lists' => [$list->id]],
            $this->rule('udp-general', [], [27015, 27016]),
        ]);
        $this->acl = $prepared['acl'];
        $this->bindings->storeBindings(self::PROFILE, '203.0.113.10', $prepared['bindings']);

        return $list->refresh();
    }

    public function testChangedListIsPushedKeepingManualIpsAndOtherRules(): void
    {
        $list = $this->boundList("45.121.184.0/22\n103.10.124.0/23\n");
        $this->assertSame(['187.94.104.229', '45.121.184.0/22', '103.10.124.0/23'], $this->acl[0]['sip_list']);

        $this->listBody = "45.121.184.0/22\n146.66.152.0/21\n2001:db8::/32\n";
        $log = $this->refresh->refresh($list);

        $this->assertSame('changed', $log->status);
        $this->assertSame(['146.66.152.0/21'], $log->added);
        $this->assertSame(['103.10.124.0/23'], $log->removed);
        $this->assertSame(1, $log->ignored_ipv6);
        $this->assertSame('updated', $log->profiles[0]['status']);
        $this->assertSame(1, $this->puts);

        $this->assertEqualsCanonicalizing(['187.94.104.229', '45.121.184.0/22', '146.66.152.0/21'], $this->acl[0]['sip_list']);
        $this->assertSame([], $this->acl[1]['sip_list']);
        $this->assertSame([27015, 27016], $this->acl[1]['dport_list']);

        $binding = GcoreIpListBinding::query()->firstOrFail();
        $this->assertSame('ok', $binding->status);
        $this->assertEqualsCanonicalizing(['45.121.184.0/22', '146.66.152.0/21'], $binding->applied_sips);

        // Editor view shows only the manual IP plus the list id.
        $annotated = $this->bindings->annotate(self::PROFILE, $this->acl);
        $this->assertSame(['187.94.104.229'], $annotated['acl'][0]['sip_list']);
        $this->assertSame([$list->id], $annotated['acl'][0]['ip_lists']);
        $this->assertSame([], $annotated['orphans']);
    }

    public function testUnchangedListDoesNotCallGcore(): void
    {
        $list = $this->boundList("45.121.184.0/22\n");

        $this->assertSame('unchanged', $this->refresh->refresh($list)->status);
        $this->assertSame(0, $this->puts);
    }

    public function testEmptyOrFailedDownloadKeepsPreviousVersion(): void
    {
        $list = $this->boundList("45.121.184.0/22\n103.10.124.0/23\n");
        $before = $this->acl;

        $this->listBody = "# nothing here\n2001:db8::/32\n";
        $log = $this->refresh->refresh($list, 'force');
        $this->assertSame('rejected', $log->status);

        $this->listStatus = 503;
        $this->assertSame('error', $this->refresh->refresh($list)->status);

        $list->refresh();
        $this->assertSame(['45.121.184.0/22', '103.10.124.0/23'], $list->entryList());
        $this->assertSame('error', $list->last_status);
        $this->assertSame(0, $this->puts);
        $this->assertSame($before, $this->acl);
    }

    public function testLargeShrinkIsRejectedUnlessForced(): void
    {
        $list = $this->boundList("1.0.0.0/24\n2.0.0.0/24\n3.0.0.0/24\n4.0.0.0/24\n");

        $this->listBody = "1.0.0.0/24\n";
        $log = $this->refresh->refresh($list);
        $this->assertSame('rejected', $log->status);
        $this->assertSame(['2.0.0.0/24', '3.0.0.0/24', '4.0.0.0/24'], $log->removed);
        $this->assertSame(4, $list->refresh()->entry_count);
        $this->assertSame(0, $this->puts);

        $this->assertSame('changed', $this->refresh->refresh($list, 'force')->status);
        $this->assertSame(['187.94.104.229', '1.0.0.0/24'], $this->acl[0]['sip_list']);
    }

    public function testRuleChangedOutsidePanelBecomesOrphanAndIsNotTouched(): void
    {
        $list = $this->boundList("45.121.184.0/22\n");
        $this->acl[0]['dport_list'] = [27020];
        $before = $this->acl;

        $this->listBody = "45.121.184.0/22\n146.66.152.0/21\n";
        $log = $this->refresh->refresh($list);

        $this->assertSame('orphan', $log->profiles[0]['status']);
        $this->assertSame(0, $this->puts);
        $this->assertSame($before, $this->acl);
        $this->assertSame('orphan', GcoreIpListBinding::query()->firstOrFail()->status);
    }

    public function testFailedPushIsRetriedOnNextRun(): void
    {
        $list = $this->boundList("45.121.184.0/22\n");

        $this->gcoreDown = true;
        $this->listBody = "45.121.184.0/22\n146.66.152.0/21\n";
        $log = $this->refresh->refresh($list);
        $this->assertSame('changed', $log->status);
        $this->assertSame('error', $log->profiles[0]['status']);
        $this->assertSame('error', GcoreIpListBinding::query()->firstOrFail()->status);

        // Next scheduled run: list unchanged, but the pending binding is pushed.
        $this->gcoreDown = false;
        $results = $this->bindings->applyPending();
        $this->assertSame('updated', $results[0]['status']);
        $this->assertSame(['187.94.104.229', '45.121.184.0/22', '146.66.152.0/21'], $this->acl[0]['sip_list']);
        $this->assertSame([], $this->bindings->applyPending());
    }

    public function testListWithoutEntriesCannotBeSelected(): void
    {
        $list = GcoreIpList::query()->create(['name' => 'Empty', 'url' => self::LIST_URL, 'refresh_hours' => 24, 'enabled' => true]);

        $this->expectException(DisplayException::class);
        $this->bindings->prepareForSave([$this->rule('allowlist', []) + ['ip_lists' => [$list->id]]]);
    }
}
