<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Models\GcoreAttackEvent;
use Pterodactyl\Services\Gcore\GcoreAttackSyncService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;

class AntiDdosController extends ClientApiController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * List stored Gcore attack events for this server's protected IP(s).
     */
    public function index(GetServerRequest $request, Server $server): array
    {
        $this->assertRootAdmin($request);

        $ips = GcoreAttackSyncService::protectedIpsForServer($server);
        $days = max(1, min(90, (int) $request->query('days', 30)));

        if ($ips === []) {
            return [
                'object' => 'anti_ddos_attacks',
                'attributes' => [
                    'supported' => false,
                    'ips' => [],
                    'days' => $days,
                    'attacks' => [],
                ],
            ];
        }

        $attacks = GcoreAttackEvent::query()
            ->whereIn('targeted_ip', $ips)
            ->where('attack_start_time', '>=', now()->subDays($days))
            ->orderByDesc('attack_start_time')
            ->limit(100)
            ->get()
            ->map(fn (GcoreAttackEvent $event) => $this->summaryPayload($event))
            ->values()
            ->all();

        return [
            'object' => 'anti_ddos_attacks',
            'attributes' => [
                'supported' => true,
                'ips' => $ips,
                'days' => $days,
                'attacks' => $attacks,
            ],
        ];
    }

    /**
     * Return a single stored attack event (with detail when available).
     */
    public function view(GetServerRequest $request, Server $server, string $attack): array
    {
        $this->assertRootAdmin($request);

        $ips = GcoreAttackSyncService::protectedIpsForServer($server);
        if ($ips === []) {
            throw new NotFoundHttpException();
        }

        /** @var GcoreAttackEvent|null $event */
        $event = GcoreAttackEvent::query()
            ->where('gcore_event_id', $attack)
            ->whereIn('targeted_ip', $ips)
            ->first();

        if ($event === null) {
            throw new NotFoundHttpException();
        }

        return [
            'object' => 'anti_ddos_attack',
            'attributes' => $this->detailPayload($event),
        ];
    }

    private function assertRootAdmin(Request $request): void
    {
        if (!$request->user()?->root_admin) {
            throw new AccessDeniedHttpException('Only root administrators can access Anti-DDoS data.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(GcoreAttackEvent $event): array
    {
        $detail = is_array($event->detail) ? $event->detail : [];
        $topPorts = $detail['attack_top_destination_ports'] ?? [];

        return [
            'id' => $event->gcore_event_id,
            'alert_type' => $event->alert_type,
            'targeted_ip' => $event->targeted_ip,
            'attack_start_time' => optional($event->attack_start_time)->toAtomString(),
            'attack_end_time' => optional($event->attack_end_time)->toAtomString(),
            'attack_power_bps' => $event->attack_power_bps,
            'attack_power_pps' => $event->attack_power_pps,
            'source_ip_count' => $event->source_ip_count,
            'ongoing' => $event->attack_end_time === null,
            'has_detail' => (bool) $event->detail_fetched,
            'top_destination_ports' => array_slice(is_array($topPorts) ? $topPorts : [], 0, 5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(GcoreAttackEvent $event): array
    {
        $detail = is_array($event->detail) ? $event->detail : [];

        return array_merge($this->summaryPayload($event), [
            'attack_traffic' => $detail['attack_traffic'] ?? [],
            'attack_packet_sizes' => $detail['attack_packet_sizes'] ?? [],
            'attack_top_source_countries' => $detail['attack_top_source_countries'] ?? [],
            'attack_top_source_ips' => $detail['attack_top_source_ips'] ?? [],
            'attack_top_source_ports' => $detail['attack_top_source_ports'] ?? [],
            'attack_top_destination_ports' => $detail['attack_top_destination_ports'] ?? [],
            'attack_top_protocols' => $detail['attack_top_protocols'] ?? [],
        ]);
    }
}
