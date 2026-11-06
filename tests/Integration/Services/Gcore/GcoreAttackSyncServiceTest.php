<?php

namespace Pterodactyl\Tests\Integration\Services\Gcore;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\GcoreAttackEvent;
use Pterodactyl\Models\NodeGcoreIp;
use Pterodactyl\Services\Gcore\GcoreAttackSyncService;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class GcoreAttackSyncServiceTest extends IntegrationTestCase
{
    public function testSyncUpsertsEventSummariesAndDetails(): void
    {
        Http::fake([
            'api.gcore.com/security/notifier/v1/event_logs*' => Http::response([
                'count' => 1,
                'limit' => 100,
                'offset' => 0,
                'results' => [[
                    'id' => '11111111-1111-1111-1111-111111111111',
                    'alert_type' => 'ddos_alert',
                    'targeted_ip_addresses' => '203.0.113.10',
                    'attack_start_time' => now()->subHour()->toIso8601String(),
                    'attack_end_time' => null,
                    'attack_power_bps' => 1500000000,
                    'attack_power_pps' => 1200000,
                    'number_of_ip_involved_in_attack' => 42,
                    'advanced_stats_ready' => true,
                ]],
            ]),
            'api.gcore.com/security/notifier/v1/event_logs/11111111-1111-1111-1111-111111111111' => Http::response([
                'id' => '11111111-1111-1111-1111-111111111111',
                'alert_type' => 'ddos_alert',
                'targeted_ip_addresses' => '203.0.113.10',
                'attack_start_time' => now()->subHour()->toIso8601String(),
                'attack_end_time' => now()->subMinutes(10)->toIso8601String(),
                'attack_power_bps' => 1500000000,
                'attack_power_pps' => 1200000,
                'number_of_ip_involved_in_attack' => 42,
                'advanced_stats_ready' => true,
                'attack_traffic' => [['dst_addr' => '203.0.113.10', 'total_bytes' => 100, 'total_packets' => 10, 'interval_start_unix' => time()]],
                'attack_top_destination_ports' => [['dst_port' => 27015, 'protocol' => 'UDP', 'bps' => 1, 'bps_percentage' => 90]],
                'attack_top_protocols' => [],
                'attack_top_source_countries' => [],
                'attack_top_source_ips' => [],
                'attack_top_source_ports' => [],
                'attack_packet_sizes' => [],
            ]),
        ]);

        $service = new GcoreAttackSyncService(new GcoreClient('test-key'));
        $stats = $service->sync(7, true);

        $this->assertSame(1, $stats['upserted']);
        $this->assertSame(1, $stats['details']);

        $event = GcoreAttackEvent::query()->where('gcore_event_id', '11111111-1111-1111-1111-111111111111')->first();
        $this->assertNotNull($event);
        $this->assertSame('203.0.113.10', $event->targeted_ip);
        $this->assertTrue($event->detail_fetched);
        $this->assertSame(27015, $event->detail['attack_top_destination_ports'][0]['dst_port']);
    }

    public function testProtectedIpsForServerUsesNodeGcoreIntersection(): void
    {
        $server = $this->createServerModel(['status' => null]);
        $ip = $server->allocation->ip;

        NodeGcoreIp::query()->create([
            'node_id' => $server->node_id,
            'ip' => $ip,
        ]);

        $ips = GcoreAttackSyncService::protectedIpsForServer($server->fresh(['allocations']));

        $this->assertSame([$ip], $ips);
    }
}
