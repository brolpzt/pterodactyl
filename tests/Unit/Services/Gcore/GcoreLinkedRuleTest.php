<?php

namespace Pterodactyl\Tests\Unit\Services\Gcore;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Models\GcoreLinkedRule;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Services\Gcore\GcoreLinkedRuleService;
use Pterodactyl\Services\Gcore\GcoreFirewallSyncService;

class GcoreLinkedRuleTest extends TestCase
{
    private function def(int $id, string $name, string $policy, ?string $proto, array $sips, string $mode): GcoreLinkedRule
    {
        $def = new GcoreLinkedRule(['name' => $name, 'policy' => $policy, 'proto' => $proto, 'sip_list' => $sips, 'mode' => $mode]);
        $def->id = $id;

        return $def;
    }

    private function rule(string $policy, array $protos, array $dports = [], array $extra = []): array
    {
        return array_merge([
            'policy' => $policy,
            'sip_list' => [],
            'dport_list' => $dports,
            'proto_list' => $protos,
            'sport_list' => [],
        ], $extra);
    }

    private function defs(): \Illuminate\Support\Collection
    {
        return collect([
            $this->def(1, 'CS 1.6 BOOST', 'allowlist', 'udp', ['216.238.125.59'], GcoreLinkedRule::MODE_DPORT),
            $this->def(2, 'Origem = destino', 'DROP', 'udp', [], GcoreLinkedRule::MODE_SAME_PORT),
        ])->keyBy('id');
    }

    private function plan(array $acl, array $game, array $links, array $panel, ?\Illuminate\Support\Collection $defs = null): array
    {
        $service = new GcoreFirewallSyncService($this->createMock(PortSlotSyncService::class));

        return $service->planAcl($acl, $game, $panel, $defs ?? $this->defs(), $links);
    }

    /** Shape of profile 20178 on 2026-11-10: a new CS server on 27050 joins all three CS rules. */
    public function testNewServerPortJoinsLinkedRulesInPlace(): void
    {
        $cs = [27015, 27017, 27047];
        $acl = [
            $this->rule('ratelimiter-low', ['icmp']),
            $this->rule('ratelimiter-low', ['udp'], [], ['sport_list' => range(27010, 27040)]),
            $this->rule('allowlist', ['udp'], $cs, ['sip_list' => ['216.238.125.59/32']]),
            $this->rule('DROP', ['udp'], [27017, 27047], ['sport_list' => [27017, 27047]]),
            $this->rule('counter-strike-16-gold', ['udp'], $cs),
            $this->rule('DROP', ['any', 'tcp', 'udp']),
        ];
        $all = [27015, 27017, 27047, 27050];

        $plan = $this->plan($acl, ['counter-strike-16-gold' => ['udp' => $all]], [1 => $all, 2 => $all], $all);

        $this->assertCount(6, $plan['acl']);
        $this->assertSame($acl[0], $plan['acl'][0]);
        $this->assertSame($acl[1], $plan['acl'][1]);
        $this->assertSame($all, $plan['acl'][2]['dport_list']);
        $this->assertSame(['216.238.125.59/32'], $plan['acl'][2]['sip_list']);
        $this->assertSame([], $plan['acl'][2]['sport_list']);
        $this->assertSame($all, $plan['acl'][3]['dport_list']);
        $this->assertSame($all, $plan['acl'][3]['sport_list']);
        $this->assertSame($all, $plan['acl'][4]['dport_list']);
        $this->assertSame([], $plan['acl'][5]['dport_list']);
        $this->assertArrayNotHasKey(GcoreLinkedRuleService::MARK, $plan['acl'][2]);
        $this->assertCount(2, $plan['linked']);
    }

    public function testMissingLinkedRulesAreCreatedAtTheTop(): void
    {
        $acl = [
            $this->rule('counter-strike-16-gold', ['udp'], [27015]),
            $this->rule('DROP', ['any']),
        ];

        $plan = $this->plan($acl, ['counter-strike-16-gold' => ['udp' => [27015]]], [1 => [27015], 2 => [27015]], [27015]);

        $this->assertCount(4, $plan['acl']);
        $this->assertSame(['allowlist', 'DROP', 'counter-strike-16-gold', 'DROP'], array_column($plan['acl'], 'policy'));
        $this->assertSame(['216.238.125.59'], $plan['acl'][0]['sip_list']);
        $this->assertSame([27015], $plan['acl'][1]['sport_list']);
        $this->assertSame([27015], $plan['acl'][1]['dport_list']);
    }

    public function testRuleLeftWithoutPortsIsRemovedButManualPortsStay(): void
    {
        $acl = [
            // 5000 is not a panel allocation: a manual port, kept.
            $this->rule('allowlist', ['udp'], [5000, 27015], ['sip_list' => ['216.238.125.59']]),
            $this->rule('DROP', ['udp'], [27015], ['sport_list' => [27015]]),
            $this->rule('DROP', ['any']),
        ];

        $plan = $this->plan($acl, [], [], [27015]);

        $this->assertCount(2, $plan['acl']);
        $this->assertSame([5000], $plan['acl'][0]['dport_list']);
        $this->assertSame([], $plan['acl'][1]['dport_list'], 'the catch-all DROP is untouched');
        $this->assertSame('DROP', $plan['acl'][1]['policy']);
    }

    public function testLinkedRuleDoesNotHideGamePortsOfTheSamePolicy(): void
    {
        // Egg game slot uses allowlist/udp too: the BOOST rule must not count as "already covered".
        $acl = [
            $this->rule('allowlist', ['udp'], [27015], ['sip_list' => ['216.238.125.59']]),
            $this->rule('DROP', ['any']),
        ];

        $plan = $this->plan($acl, ['allowlist' => ['udp' => [27015]]], [1 => [27015]], [27015]);

        $policies = array_map(fn ($r) => $r['policy'] . ':' . implode(',', $r['sip_list']), $plan['acl']);
        $this->assertSame(['allowlist:216.238.125.59', 'allowlist:', 'DROP:'], $policies);
    }

    public function testUnusedDefinitionsAndCatchAllRulesAreNeverTouched(): void
    {
        $acl = [
            $this->rule('allowlist', ['udp'], [27015], ['sip_list' => ['216.238.125.59']]),
            $this->rule('DROP', ['udp']), // empty dport: never matched by a linked rule
        ];

        // No definitions in use: nothing changes even though no server wants these ports.
        $plan = $this->plan($acl, [], [], [27015], collect());
        $this->assertSame($acl, $plan['acl']);
        $this->assertSame([], $plan['linked']);

        $defs = $this->defs();
        $this->assertFalse(GcoreLinkedRuleService::matches($acl[1], $defs[2]));
        $this->assertTrue(GcoreLinkedRuleService::matches($acl[0], $defs[1]));
        $this->assertFalse(GcoreLinkedRuleService::matches(
            $this->rule('DROP', ['udp'], [27015, 27017], ['sport_list' => [27015]]),
            $defs[2],
        ), 'same_port needs sport = dport');
    }
}
