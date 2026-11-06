<?php

namespace Pterodactyl\Tests\Unit\Services\Gcore;

use Pterodactyl\Models\GcoreAttackEvent;
use Pterodactyl\Services\Gcore\GcoreAttackSyncService;
use PHPUnit\Framework\TestCase;

class GcoreAttackPortScopeTest extends TestCase
{
    public function testEventTouchesOnlyMatchingDestinationPorts(): void
    {
        $event = new GcoreAttackEvent([
            'detail_fetched' => true,
            'detail' => [
                'attack_top_destination_ports' => [
                    ['dst_port' => 27015, 'protocol' => 'UDP', 'bps' => 100, 'bps_percentage' => 60],
                    ['dst_port' => 80, 'protocol' => 'UDP', 'bps' => 40, 'bps_percentage' => 40],
                ],
            ],
        ]);

        $this->assertTrue(GcoreAttackSyncService::eventTouchesPorts($event, [27015]));
        $this->assertFalse(GcoreAttackSyncService::eventTouchesPorts($event, [25565]));

        $matched = GcoreAttackSyncService::matchingDestinationPorts($event, [27015]);
        $this->assertCount(1, $matched);
        $this->assertSame(27015, $matched[0]['dst_port']);
    }
}
