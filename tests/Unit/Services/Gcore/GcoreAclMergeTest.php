<?php

namespace Pterodactyl\Tests\Unit\Services\Gcore;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Services\Gcore\GcoreFirewallSyncService;

class GcoreAclMergeTest extends TestCase
{
    private function merge(
        array $acl,
        array $desiredByPolicyProto,
        array $panelAllocationPorts = [],
    ): array {
        $service = new GcoreFirewallSyncService(
            $this->createMock(PortSlotSyncService::class)
        );
        $method = new ReflectionMethod(GcoreFirewallSyncService::class, 'mergeGroupedAcl');
        $method->setAccessible(true);

        return $method->invoke($service, $acl, $desiredByPolicyProto, $panelAllocationPorts);
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

    public function testDropInTheMiddleKeepsSameIndexWhenAddingGamePort(): void
    {
        $acl = [
            $this->rule('allowlist', ['any'], [22]),
            $this->rule('geo', ['any']),
            $this->rule('tcp-server', ['tcp'], [8080]),
            $this->rule('DROP', ['icmp']),
            $this->rule('DROP', ['tcp'], [22]),
            $this->rule('rust', ['udp'], [27015]),
            $this->rule('default-udp', ['udp']),
        ];

        $result = $this->merge(
            $acl,
            ['rust' => ['udp' => [27015, 27016]]],
            [27015, 27016],
        );

        $out = $result['acl'];

        $this->assertSame('DROP', $out[3]['policy']);
        $this->assertSame(['icmp'], $out[3]['proto_list']);
        $this->assertSame([], $out[3]['dport_list']);

        $this->assertSame('DROP', $out[4]['policy']);
        $this->assertSame(['tcp'], $out[4]['proto_list']);
        $this->assertSame([22], $out[4]['dport_list']);

        $this->assertSame('rust', $out[5]['policy']);
        $this->assertSame([27015, 27016], $out[5]['dport_list']);
        $this->assertSame('default-udp', $out[6]['policy']);

        $this->assertContains(27016, $result['added']['rust']);
    }

    public function testRemovingPanelPortDoesNotMoveDrop(): void
    {
        $acl = [
            $this->rule('allowlist', ['any']),
            $this->rule('DROP', ['icmp']),
            $this->rule('rust', ['udp'], [27015, 27016]),
            $this->rule('default-udp', ['udp']),
        ];

        $result = $this->merge(
            $acl,
            ['rust' => ['udp' => [27015]]],
            [27015, 27016],
        );

        $out = $result['acl'];
        $this->assertSame('DROP', $out[1]['policy']);
        $this->assertSame([27015], $out[2]['dport_list']);
        $this->assertContains(27016, $result['removed']['rust']);
    }

    public function testAppendsNewGameRuleBeforeTrailingDrop(): void
    {
        $acl = [
            $this->rule('allowlist', ['any']),
            $this->rule('rust', ['udp'], [27015]),
            $this->rule('DROP', ['any']),
        ];

        $result = $this->merge(
            $acl,
            [
                'rust' => ['udp' => [27015]],
                'counter-strike-16' => ['udp' => [27005]],
            ],
            [27015, 27005],
        );

        $out = $result['acl'];
        $this->assertCount(4, $out);
        $this->assertSame('allowlist', $out[0]['policy']);
        $this->assertSame('rust', $out[1]['policy']);
        $this->assertSame([27015], $out[1]['dport_list']);
        $this->assertSame('counter-strike-16', $out[2]['policy']);
        $this->assertSame([27005], $out[2]['dport_list']);
        $this->assertSame('DROP', $out[3]['policy']);
    }

    public function testAppendsNewGameRuleBeforeTrailingDropEvenWithDefaultAfter(): void
    {
        $acl = [
            $this->rule('allowlist', ['any']),
            $this->rule('DROP', ['icmp']),
            $this->rule('rust', ['udp'], [27015]),
            $this->rule('DROP', ['any']),
            $this->rule('default-udp', ['udp']),
        ];

        $result = $this->merge(
            $acl,
            [
                'rust' => ['udp' => [27015]],
                'counter-strike-16' => ['udp' => [27005]],
            ],
            [27015, 27005],
        );

        $out = $result['acl'];
        $this->assertSame('DROP', $out[1]['policy']);
        $this->assertSame(['icmp'], $out[1]['proto_list']);
        $this->assertSame('rust', $out[2]['policy']);
        $this->assertSame('counter-strike-16', $out[3]['policy']);
        $this->assertSame('DROP', $out[4]['policy']);
        $this->assertSame(['any'], $out[4]['proto_list']);
        $this->assertSame('default-udp', $out[5]['policy']);
    }

    public function testAppendsNewGameRuleBeforeDefaultWhenNoTrailingDrop(): void
    {
        $acl = [
            $this->rule('allowlist', ['any']),
            $this->rule('DROP', ['tcp'], [22]),
            $this->rule('default-udp', ['udp']),
        ];

        $result = $this->merge(
            $acl,
            ['rust' => ['udp' => [7777]]],
            [7777],
        );

        $out = $result['acl'];
        // Trailing region after skip defaults is DROP tcp/22 → insert before that DROP.
        $this->assertCount(4, $out);
        $this->assertSame('allowlist', $out[0]['policy']);
        $this->assertSame('rust', $out[1]['policy']);
        $this->assertSame([7777], $out[1]['dport_list']);
        $this->assertSame('DROP', $out[2]['policy']);
        $this->assertSame([22], $out[2]['dport_list']);
        $this->assertSame('default-udp', $out[3]['policy']);
    }

    public function testExistingSameFilterOnlyAddsPortsInPlace(): void
    {
        $acl = [
            $this->rule('allowlist', ['any']),
            $this->rule('counter-strike-16', ['udp'], [27005]),
            $this->rule('DROP', ['any']),
        ];

        $result = $this->merge(
            $acl,
            ['counter-strike-16' => ['udp' => [27005, 27015]]],
            [27005, 27015],
        );

        $out = $result['acl'];
        $this->assertCount(3, $out);
        $this->assertSame('counter-strike-16', $out[1]['policy']);
        $this->assertSame([27005, 27015], $out[1]['dport_list']);
        $this->assertSame('DROP', $out[2]['policy']);
        $this->assertContains(27015, $result['added']['counter-strike-16']);
    }

    public function testNeverCreatesDropRules(): void
    {
        $acl = [
            $this->rule('allowlist', ['any']),
            $this->rule('default-udp', ['udp']),
        ];

        $result = $this->merge($acl, ['rust' => ['udp' => [1]]], [1]);
        $policies = array_column($result['acl'], 'policy');
        $this->assertNotContains('DROP', $policies);
    }

    public function testNoDeltaWhenPortsAlreadyPresent(): void
    {
        $acl = [
            $this->rule('DROP', ['icmp']),
            $this->rule('rust', ['udp'], [27015]),
        ];

        $result = $this->merge(
            $acl,
            ['rust' => ['udp' => [27015]]],
            [27015],
        );

        $this->assertSame([], $result['added']);
        $this->assertSame([], $result['removed']);
        $this->assertSame('DROP', $result['acl'][0]['policy']);
    }

    public function testRatelimiterNewServerJoinsExistingRule(): void
    {
        $acl = [
            $this->rule('DROP', ['icmp']),
            $this->rule('ratelimiter-high', ['udp'], [4976, 4977]),
            $this->rule('DROP', []),
        ];

        $out = $this->merge($acl, ['ratelimiter-high' => ['udp' => [4976, 4977, 4978]]], [4976, 4977, 4978])['acl'];

        $this->assertCount(3, $out);
        $this->assertSame([4976, 4977, 4978], $out[1]['dport_list']);
    }

    public function testRatelimiterDuplicateRowsAreCollapsedAndDeletedPortRemoved(): void
    {
        $acl = [
            $this->rule('DROP', ['icmp']),
            $this->rule('ratelimiter-high', ['udp'], [4976, 4977, 9999]),
            $this->rule('ratelimiter-high', ['udp'], [4977, 4978, 9999]),
            $this->rule('DROP', []),
        ];

        // 4977 belonged to a deleted server; 9999 is a manual port.
        $result = $this->merge($acl, ['ratelimiter-high' => ['udp' => [4976, 4978]]], [4976, 4977, 4978]);
        $out = $result['acl'];

        $this->assertCount(3, $out);
        $this->assertSame([4976, 4978, 9999], $out[1]['dport_list']);
        $this->assertSame('DROP', $out[2]['policy']);
        $this->assertContains(4977, $result['removed']['ratelimiter-high']);
    }

    public function testPolicyChangeMovesPortsInsteadOfDuplicating(): void
    {
        $acl = [
            $this->rule('DROP', ['icmp']),
            $this->rule('ratelimiter-high', ['udp'], [4976, 4977]),
            $this->rule('DROP', []),
        ];

        $out = $this->merge($acl, ['udp-general' => ['udp' => [4976, 4977]]], [4976, 4977])['acl'];

        $this->assertCount(3, $out);
        $this->assertSame('DROP', $out[0]['policy']);
        $this->assertSame('udp-general', $out[1]['policy']);
        $this->assertSame([4976, 4977], $out[1]['dport_list']);
    }

    public function testTcpServerKeepsManualPortsAndManagesPanelPorts(): void
    {
        $acl = [
            $this->rule('tcp-server', ['tcp'], [2022]),
            $this->rule('tcp-server', ['tcp'], [8080]),
            $this->rule('DROP', []),
        ];

        $out = $this->merge($acl, ['tcp-server' => ['tcp' => [7777]]], [7777, 7778])['acl'];

        $this->assertSame([2022, 7777], $out[0]['dport_list']);
        $this->assertSame([8080], $out[1]['dport_list']);
    }
}
