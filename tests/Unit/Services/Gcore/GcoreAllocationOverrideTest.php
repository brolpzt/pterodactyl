<?php

namespace Pterodactyl\Tests\Unit\Services\Gcore;

use Pterodactyl\Tests\TestCase;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Allocation;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Services\Eggs\PortSlotSyncService;

class GcoreAllocationOverrideTest extends TestCase
{
    private function service(): PortSlotSyncService
    {
        return new PortSlotSyncService($this->createMock(ConnectionInterface::class));
    }

    private function egg(): Egg
    {
        $egg = new Egg();
        $egg->setRawAttributes([
            'id' => 1,
            'port_slots' => json_encode([
                ['env_variable' => 'SERVER_PORT', 'name' => 'Game Port', 'gcore_policy' => 'counter-strike-16', 'gcore_proto' => 'udp'],
            ]),
        ]);

        return $egg;
    }

    private function allocation(array $attributes): Allocation
    {
        $allocation = new Allocation();
        $allocation->setRawAttributes(array_merge(['id' => 10, 'port' => 27015, 'server_id' => 325], $attributes));

        return $allocation;
    }

    public function testEggPolicyWhenNoOverride(): void
    {
        $resolved = $this->service()->resolveGcoreForAllocation($this->egg(), $this->allocation([]), 10);

        $this->assertSame(['policy' => 'counter-strike-16', 'proto' => 'udp'], $resolved);
    }

    public function testOverrideWinsForSameServer(): void
    {
        $allocation = $this->allocation([
            'gcore_override_policy' => 'udp-general',
            'gcore_override_proto' => 'udp',
            'gcore_override_server_id' => 325,
        ]);

        $this->assertSame(['policy' => 'udp-general', 'proto' => 'udp'], $this->service()->resolveGcoreForAllocation($this->egg(), $allocation, 10));
        $this->assertSame(['policy' => 'counter-strike-16', 'proto' => 'udp'], $this->service()->resolveEggGcoreForAllocation($this->egg(), $allocation, 10));
    }

    public function testOverrideIgnoredWhenPortMovedToAnotherServer(): void
    {
        $allocation = $this->allocation([
            'server_id' => 999,
            'gcore_override_policy' => 'udp-general',
            'gcore_override_proto' => 'udp',
            'gcore_override_server_id' => 325,
        ]);

        $this->assertNull($allocation->activeGcoreOverride());
        $this->assertSame('counter-strike-16', $this->service()->resolveGcoreForAllocation($this->egg(), $allocation, 10)['policy']);
    }

    public function testOverrideWithAnyProtoMapsToNull(): void
    {
        $allocation = $this->allocation([
            'gcore_override_policy' => 'allowlist',
            'gcore_override_proto' => 'any',
            'gcore_override_server_id' => 325,
        ]);

        $this->assertSame(['policy' => 'allowlist', 'proto' => null], $allocation->activeGcoreOverride());
    }
}
