<?php

namespace Pterodactyl\Services\Gcore;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\GcoreAttackEvent;
use Pterodactyl\Models\NodeGcoreIp;
use Pterodactyl\Models\Server;

class GcoreAttackSyncService
{
    public const RETENTION_DAYS = 90;

    private GcoreClient $client;

    public function __construct(?GcoreClient $client = null)
    {
        $this->client = $client ?? GcoreClient::fromConfig();
    }

    /**
     * Pull recent Gcore event logs into the local database.
     *
     * @return array{upserted: int, details: int, purged: int, errors: int}
     */
    public function sync(int $days = 7, bool $fetchDetails = true): array
    {
        $upserted = 0;
        $details = 0;
        $errors = 0;

        $dateFrom = Carbon::now('UTC')->subDays(max(1, $days))->toDateString();
        $dateTo = Carbon::now('UTC')->addDay()->toDateString();
        $offset = 0;
        $limit = 100;

        do {
            try {
                $page = $this->client->listEventLogs([
                    'alert_type' => 'ddos_alert',
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'ordering' => '-attack_start_time',
                    'limit' => $limit,
                    'offset' => $offset,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Gcore attack sync list failed', ['error' => $exception->getMessage()]);

                return [
                    'upserted' => $upserted,
                    'details' => $details,
                    'purged' => 0,
                    'errors' => $errors + 1,
                ];
            }

            $results = $page['results'] ?? [];
            if (!is_array($results)) {
                break;
            }

            foreach ($results as $row) {
                if (!is_array($row) || empty($row['id'])) {
                    continue;
                }

                try {
                    $this->upsertSummary($row);
                    $upserted++;
                } catch (\Throwable $exception) {
                    $errors++;
                    Log::warning('Gcore attack sync upsert failed', [
                        'event_id' => $row['id'] ?? null,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $count = (int) ($page['count'] ?? 0);
            $offset += $limit;
        } while ($offset < $count && $results !== []);

        if ($fetchDetails) {
            $details = $this->fetchMissingDetails();
        }

        $purged = $this->purgeOlderThanDays(self::RETENTION_DAYS);

        return compact('upserted', 'details', 'purged', 'errors');
    }

    public function fetchMissingDetails(int $limit = 40): int
    {
        $fetched = 0;

        $events = GcoreAttackEvent::query()
            ->where('advanced_stats_ready', true)
            ->where(function ($query) {
                $query->where('detail_fetched', false)
                    ->orWhereNull('attack_end_time');
            })
            ->orderByDesc('attack_start_time')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            try {
                $detail = $this->client->getEventLog($event->gcore_event_id);
                $this->applyDetail($event, $detail);
                $fetched++;
            } catch (\Throwable $exception) {
                Log::warning('Gcore attack detail fetch failed', [
                    'event_id' => $event->gcore_event_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $fetched;
    }

    public function purgeOlderThanDays(int $days = self::RETENTION_DAYS): int
    {
        return GcoreAttackEvent::query()
            ->where('attack_start_time', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Protected Gcore IPs assigned to this server (allocation IP ∩ node_gcore_ips).
     *
     * @return list<string>
     */
    public static function protectedIpsForServer(Server $server): array
    {
        return array_values(array_unique(array_map(
            static fn (array $endpoint) => $endpoint['ip'],
            self::protectedEndpointsForServer($server)
        )));
    }

    /**
     * Protected Gcore IP:port endpoints that belong to this server only.
     *
     * @return list<array{ip: string, port: int}>
     */
    public static function protectedEndpointsForServer(Server $server): array
    {
        $server->loadMissing(['allocations']);

        $allocationIps = $server->allocations
            ->pluck('ip')
            ->filter()
            ->map(static fn ($ip) => trim((string) $ip))
            ->unique()
            ->values();

        if ($allocationIps->isEmpty()) {
            return [];
        }

        $protectedIps = NodeGcoreIp::query()
            ->where('node_id', $server->node_id)
            ->whereIn('ip', $allocationIps->all())
            ->pluck('ip')
            ->map(static fn ($ip) => trim((string) $ip))
            ->unique()
            ->all();

        if ($protectedIps === []) {
            return [];
        }

        $protectedLookup = array_fill_keys($protectedIps, true);

        return $server->allocations
            ->filter(static function ($allocation) use ($protectedLookup) {
                $ip = trim((string) $allocation->ip);

                return isset($protectedLookup[$ip]) && (int) $allocation->port > 0;
            })
            ->map(static fn ($allocation) => [
                'ip' => trim((string) $allocation->ip),
                'port' => (int) $allocation->port,
            ])
            ->unique(static fn (array $endpoint) => $endpoint['ip'] . ':' . $endpoint['port'])
            ->values()
            ->all();
    }

    /**
     * @param list<array{ip: string, port: int}> $endpoints
     * @return array<string, list<int>>
     */
    public static function portsByIp(array $endpoints): array
    {
        $map = [];
        foreach ($endpoints as $endpoint) {
            $ip = $endpoint['ip'];
            $map[$ip] ??= [];
            $map[$ip][] = (int) $endpoint['port'];
        }

        foreach ($map as $ip => $ports) {
            $map[$ip] = array_values(array_unique($ports));
        }

        return $map;
    }

    /**
     * Whether this Gcore event touched at least one of the server's ports.
     *
     * Gcore only exposes top destination ports — ports outside the top list are invisible.
     *
     * @param list<int> $ports
     */
    public static function eventTouchesPorts(GcoreAttackEvent $event, array $ports): bool
    {
        return self::matchingDestinationPorts($event, $ports) !== [];
    }

    /**
     * @param list<int> $ports
     * @return list<array<string, mixed>>
     */
    public static function matchingDestinationPorts(GcoreAttackEvent $event, array $ports): array
    {
        if ($ports === [] || !$event->detail_fetched || !is_array($event->detail)) {
            return [];
        }

        $portLookup = array_fill_keys(array_map('intval', $ports), true);
        $rows = $event->detail['attack_top_destination_ports'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $matched = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dstPort = (int) ($row['dst_port'] ?? 0);
            if ($dstPort > 0 && isset($portLookup[$dstPort])) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsertSummary(array $row): GcoreAttackEvent
    {
        $eventId = (string) $row['id'];
        $targeted = $this->normalizeTargetedIp($row['targeted_ip_addresses'] ?? null);

        $attributes = [
            'alert_type' => (string) ($row['alert_type'] ?? 'ddos_alert'),
            'targeted_ip' => $targeted,
            'attack_start_time' => $this->parseTime($row['attack_start_time'] ?? null),
            'attack_end_time' => $this->parseTime($row['attack_end_time'] ?? null),
            'attack_power_bps' => $this->toUnsignedInt($row['attack_power_bps'] ?? null),
            'attack_power_pps' => $this->toUnsignedInt($row['attack_power_pps'] ?? null),
            'source_ip_count' => isset($row['number_of_ip_involved_in_attack'])
                ? (int) $row['number_of_ip_involved_in_attack']
                : null,
            'advanced_stats_ready' => (bool) ($row['advanced_stats_ready'] ?? false),
            'synced_at' => now(),
        ];

        /** @var GcoreAttackEvent $event */
        $event = GcoreAttackEvent::query()->updateOrCreate(
            ['gcore_event_id' => $eventId],
            $attributes
        );

        return $event;
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function applyDetail(GcoreAttackEvent $event, array $detail): void
    {
        $event->forceFill([
            'alert_type' => (string) ($detail['alert_type'] ?? $event->alert_type),
            'targeted_ip' => $this->normalizeTargetedIp($detail['targeted_ip_addresses'] ?? $event->targeted_ip),
            'attack_start_time' => $this->parseTime($detail['attack_start_time'] ?? null) ?? $event->attack_start_time,
            'attack_end_time' => $this->parseTime($detail['attack_end_time'] ?? null),
            'attack_power_bps' => $this->toUnsignedInt($detail['attack_power_bps'] ?? $event->attack_power_bps),
            'attack_power_pps' => $this->toUnsignedInt($detail['attack_power_pps'] ?? $event->attack_power_pps),
            'source_ip_count' => isset($detail['number_of_ip_involved_in_attack'])
                ? (int) $detail['number_of_ip_involved_in_attack']
                : $event->source_ip_count,
            'advanced_stats_ready' => (bool) ($detail['advanced_stats_ready'] ?? true),
            'detail_fetched' => true,
            'detail' => [
                'attack_traffic' => $detail['attack_traffic'] ?? [],
                'attack_packet_sizes' => $detail['attack_packet_sizes'] ?? [],
                'attack_top_source_countries' => $detail['attack_top_source_countries'] ?? [],
                'attack_top_source_ips' => $detail['attack_top_source_ips'] ?? [],
                'attack_top_source_ports' => $detail['attack_top_source_ports'] ?? [],
                'attack_top_destination_ports' => $detail['attack_top_destination_ports'] ?? [],
                'attack_top_protocols' => $detail['attack_top_protocols'] ?? [],
            ],
            'synced_at' => now(),
        ])->save();
    }

    private function normalizeTargetedIp(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '0.0.0.0';
        }

        // Gcore may return "1.2.3.4" or comma-separated list.
        $first = trim(explode(',', $raw)[0]);

        return $first !== '' ? $first : '0.0.0.0';
    }

    private function parseTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function toUnsignedInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        if ($number < 0) {
            return 0;
        }

        return (int) round($number);
    }
}
