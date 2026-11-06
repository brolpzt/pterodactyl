<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Pterodactyl\Models\GcoreAttackEvent;
use Pterodactyl\Services\Gcore\GcoreAttackSyncService;
use Pterodactyl\Support\TimePeriod;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;

class AntiDdosController extends ClientApiController
{
    /** @var list<string> */
    public const PERIODS = ['15m', '1h', '6h', '24h', '7d', '30d', '90d'];

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * List stored Gcore DDoS events that touched this server's protected IP:port(s).
     *
     * Query: period=15m|1h|6h|24h|7d|30d|90d (default 7d). Legacy ?days=N still accepted.
     */
    public function index(GetServerRequest $request, Server $server): array
    {
        $this->assertRootAdmin($request);

        $endpoints = GcoreAttackSyncService::protectedEndpointsForServer($server);
        $portsByIp = GcoreAttackSyncService::portsByIp($endpoints);
        $ips = array_keys($portsByIp);
        $period = $this->resolvePeriod($request);

        if ($endpoints === []) {
            return [
                'object' => 'anti_ddos_events',
                'attributes' => [
                    'supported' => false,
                    'ips' => [],
                    'endpoints' => [],
                    'period' => $period,
                    'events' => [],
                ],
            ];
        }

        $events = GcoreAttackEvent::query()
            ->whereIn('targeted_ip', $ips)
            ->where('detail_fetched', true)
            ->where('attack_start_time', '>=', TimePeriod::since($period))
            ->orderByDesc('attack_start_time')
            ->limit(300)
            ->get()
            ->filter(function (GcoreAttackEvent $event) use ($portsByIp) {
                $ports = $portsByIp[$event->targeted_ip] ?? [];

                return GcoreAttackSyncService::eventTouchesPorts($event, $ports);
            })
            ->take(100)
            ->map(function (GcoreAttackEvent $event) use ($portsByIp) {
                return $this->summaryPayload($event, $portsByIp[$event->targeted_ip] ?? []);
            })
            ->values()
            ->all();

        return [
            'object' => 'anti_ddos_events',
            'attributes' => [
                'supported' => true,
                'ips' => $ips,
                'endpoints' => $endpoints,
                'period' => $period,
                'events' => $events,
            ],
        ];
    }

    /**
     * Return a single stored DDoS event scoped to this server's ports.
     */
    public function view(GetServerRequest $request, Server $server, string $event): array
    {
        $this->assertRootAdmin($request);

        $endpoints = GcoreAttackSyncService::protectedEndpointsForServer($server);
        $portsByIp = GcoreAttackSyncService::portsByIp($endpoints);
        $ips = array_keys($portsByIp);
        if ($ips === []) {
            throw new NotFoundHttpException();
        }

        /** @var GcoreAttackEvent|null $model */
        $model = GcoreAttackEvent::query()
            ->where('gcore_event_id', $event)
            ->whereIn('targeted_ip', $ips)
            ->where('detail_fetched', true)
            ->first();

        if ($model === null) {
            throw new NotFoundHttpException();
        }

        $ports = $portsByIp[$model->targeted_ip] ?? [];
        if (!GcoreAttackSyncService::eventTouchesPorts($model, $ports)) {
            throw new NotFoundHttpException();
        }

        return [
            'object' => 'anti_ddos_event',
            'attributes' => $this->detailPayload($model, $ports),
        ];
    }

    private function assertRootAdmin(Request $request): void
    {
        if (!$request->user()?->root_admin) {
            throw new AccessDeniedHttpException('Only root administrators can access Anti-DDoS data.');
        }
    }

    private function resolvePeriod(GetServerRequest $request): string
    {
        $period = $request->query('period');
        if (!is_string($period) || $period === '') {
            $days = max(1, min(90, (int) $request->query('days', 7)));
            $period = match (true) {
                $days <= 1 => '24h',
                $days <= 7 => '7d',
                $days <= 30 => '30d',
                default => '90d',
            };
        }

        return TimePeriod::normalize($period, self::PERIODS, '7d');
    }

    /**
     * @param list<int> $ports
     * @return array<string, mixed>
     */
    private function summaryPayload(GcoreAttackEvent $event, array $ports): array
    {
        $matchedPorts = GcoreAttackSyncService::matchingDestinationPorts($event, $ports);
        $scoped = $this->scopedPower($matchedPorts);

        return [
            'id' => $event->gcore_event_id,
            'alert_type' => $event->alert_type,
            'targeted_ip' => $event->targeted_ip,
            'ports' => array_values(array_unique(array_map(
                static fn (array $row) => (int) ($row['dst_port'] ?? 0),
                $matchedPorts
            ))),
            'attack_start_time' => optional($event->attack_start_time)->toAtomString(),
            'attack_end_time' => optional($event->attack_end_time)->toAtomString(),
            // Power estimated from this server's destination-port share only (not full IP attack).
            'attack_power_bps' => $scoped['bps'],
            'attack_power_pps' => $scoped['pps'],
            'share_percentage' => $scoped['share_percentage'],
            'source_ip_count' => null,
            'ongoing' => GcoreAttackSyncService::isOngoing($event),
            'has_detail' => true,
            'top_destination_ports' => $matchedPorts,
        ];
    }

    /**
     * @param list<int> $ports
     * @return array<string, mixed>
     */
    private function detailPayload(GcoreAttackEvent $event, array $ports): array
    {
        $matchedPorts = GcoreAttackSyncService::matchingDestinationPorts($event, $ports);

        return array_merge($this->summaryPayload($event, $ports), [
            // Intentionally omit IP-wide series / other customers' ports / source intel.
            'attack_traffic' => [],
            'attack_packet_sizes' => [],
            'attack_top_source_countries' => [],
            'attack_top_source_ips' => [],
            'attack_top_source_ports' => [],
            'attack_top_destination_ports' => $matchedPorts,
            'attack_top_protocols' => $this->protocolsFromPorts($matchedPorts),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $matchedPorts
     * @return array{bps: int|null, pps: int|null, share_percentage: float|null}
     */
    private function scopedPower(array $matchedPorts): array
    {
        if ($matchedPorts === []) {
            return ['bps' => null, 'pps' => null, 'share_percentage' => null];
        }

        $bps = 0.0;
        $pps = 0.0;
        $share = 0.0;
        foreach ($matchedPorts as $row) {
            $bps += (float) ($row['bps'] ?? 0);
            $pps += (float) ($row['pps'] ?? 0);
            $share += (float) ($row['bps_percentage'] ?? 0);
        }

        return [
            'bps' => (int) round($bps),
            'pps' => (int) round($pps),
            'share_percentage' => round(min(100, $share), 2),
        ];
    }

    /**
     * @param list<array<string, mixed>> $matchedPorts
     * @return list<array<string, mixed>>
     */
    private function protocolsFromPorts(array $matchedPorts): array
    {
        $byProto = [];
        foreach ($matchedPorts as $row) {
            $proto = strtoupper(trim((string) ($row['protocol'] ?? 'UNKNOWN')));
            if ($proto === '') {
                $proto = 'UNKNOWN';
            }
            $byProto[$proto] ??= [
                'protocol' => $proto,
                'bps' => 0.0,
                'pps' => 0.0,
                'bps_percentage' => 0.0,
            ];
            $byProto[$proto]['bps'] += (float) ($row['bps'] ?? 0);
            $byProto[$proto]['pps'] += (float) ($row['pps'] ?? 0);
            $byProto[$proto]['bps_percentage'] += (float) ($row['bps_percentage'] ?? 0);
        }

        return array_values(array_map(static function (array $row) {
            return [
                'protocol' => $row['protocol'],
                'bps' => (int) round($row['bps']),
                'pps' => (int) round($row['pps']),
                'bps_percentage' => round($row['bps_percentage'], 2),
            ];
        }, $byProto));
    }
}
