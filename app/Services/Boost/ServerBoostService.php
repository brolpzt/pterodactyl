<?php

namespace Pterodactyl\Services\Boost;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Egg;
use Illuminate\Support\Str;
use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\BoostConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Server Boost: an external CS 1.6 boost system reads the list of servers through a URL that
 * carries a secret token, and calls back each time it sends a player to one of them.
 */
class ServerBoostService
{
    public const SETTING_TOKEN = 'boost:token';
    public const SETTING_EGGS = 'boost:eggs';

    public const RETENTION_DAYS = 90;

    /** range => [hours back, bucket size in hours] */
    public const RANGES = ['24h' => [24, 1], '7d' => [168, 24], '30d' => [720, 24]];

    /** Statuses that keep a server out of the list: no game is running there. */
    private const UNAVAILABLE = [Server::STATUS_SUSPENDED, Server::STATUS_INSTALLING, Server::STATUS_INSTALL_FAILED];

    public function __construct(private SettingsRepositoryInterface $settings, private Encrypter $encrypter)
    {
    }

    public function token(): ?string
    {
        $value = $this->settings->get(self::SETTING_TOKEN);
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return $this->encrypter->decrypt($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function regenerateToken(): string
    {
        $token = Str::random(48);
        $this->settings->set(self::SETTING_TOKEN, $this->encrypter->encrypt($token));

        return $token;
    }

    public function validToken(string $given): bool
    {
        $token = $this->token();

        return $token !== null && hash_equals($token, $given);
    }

    /**
     * Eggs whose servers make up the boost list. Until the admin picks them, every egg named
     * like Counter-Strike 1.6.
     *
     * @return list<int>
     */
    public function eggIds(): array
    {
        $value = $this->settings->get(self::SETTING_EGGS);
        if (is_string($value)) {
            return array_values(array_filter(array_map('intval', explode(',', $value))));
        }

        return Egg::query()
            ->where(fn ($q) => $q->where('name', 'like', '%Counter-Strike 1.6%')->orWhere('name', 'like', '%CS 1.6%'))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param list<int> $ids */
    public function setEggIds(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);
        $this->settings->set(self::SETTING_EGGS, implode(',', $ids));
    }

    public function isEligible(Server $server): bool
    {
        return in_array((int) $server->egg_id, $this->eggIds(), true);
    }

    /** Every server of the boost eggs, hidden ones included (admin view). */
    public function eligibleQuery(): Builder
    {
        return Server::query()
            ->with(['allocation', 'node'])
            ->whereIn('egg_id', $this->eggIds() ?: [0])
            ->whereNotNull('allocation_id')
            ->orderBy('id');
    }

    /** @return Collection<int, Server> what the boost system gets */
    public function listed(): Collection
    {
        return $this->eligibleQuery()
            ->where('boost_hidden', false)
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', self::UNAVAILABLE))
            ->get()
            ->filter(fn (Server $s) => self::host($s) !== '')
            ->values();
    }

    public static function isAvailable(Server $server): bool
    {
        return !in_array($server->status, self::UNAVAILABLE, true);
    }

    /** Public host: the allocation alias/IP, or the node FQDN when bound to every address. */
    public static function host(Server $server): string
    {
        $host = trim((string) ($server->allocation?->alias ?? ''));
        if (in_array($host, ['', '0.0.0.0', '::', '::0'], true)) {
            $host = trim((string) ($server->node?->fqdn ?? ''));
        }

        return $host;
    }

    public static function address(Server $server): string
    {
        return self::host($server) . ':' . (int) $server->allocation?->port;
    }

    /** @return array<string, mixed> */
    public static function entry(Server $server): array
    {
        return [
            'id' => $server->id,
            'uuid' => $server->uuid,
            'name' => $server->name,
            'address' => self::address($server),
            'ip' => self::host($server),
            'port' => (int) $server->allocation->port,
            'node' => $server->node?->name,
        ];
    }

    /**
     * Find the server a connect report is about: the panel id from the list, or ip:port.
     * Only servers of the boost eggs count.
     */
    public function resolve(?string $serverId, ?string $address): ?Server
    {
        if ($serverId !== null && ctype_digit($serverId)) {
            return $this->eligibleQuery()->whereKey((int) $serverId)->first();
        }

        if ($address === null || preg_match('/\A\[?([^\]\s]+?)\]?:(\d{1,5})\z/', trim($address), $m) !== 1) {
            return null;
        }
        $host = strtolower($m[1]);
        $port = (int) $m[2];

        return $this->eligibleQuery()
            ->whereHas('allocation', fn ($q) => $q->where('port', $port))
            ->get()
            ->first(fn (Server $s) => in_array($host, array_map('strtolower', array_filter([
                self::host($s), $s->allocation?->ip, $s->allocation?->ip_alias,
            ])), true));
    }

    /** @param array{player_name?: ?string, player_ip?: ?string, steam_id?: ?string} $player */
    public function record(Server $server, array $player, ?string $sourceIp): BoostConnection
    {
        return BoostConnection::query()->create([
            'server_id' => $server->id,
            'address' => self::address($server),
            'player_name' => self::clean($player['player_name'] ?? null, 64),
            'player_ip' => $player['player_ip'] ?? null,
            'steam_id' => self::clean($player['steam_id'] ?? null, 64),
            'source_ip' => $sourceIp,
        ]);
    }

    private static function clean(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * Dashboard numbers for a range, buckets in the stats timezone.
     *
     * @return array{
     *     total: int, players: int, servers: int, last: ?CarbonImmutable,
     *     series: list<array{label: string, title: string, count: int}>,
     *     top: list<array{server_id: ?int, name: string, address: string, count: int}>
     * }
     */
    public function stats(string $range, ?int $serverId = null): array
    {
        [$hours, $bucket] = self::RANGES[$range] ?? self::RANGES['24h'];
        $tz = (string) config('app.stats_timezone', config('app.timezone', 'UTC'));
        $now = CarbonImmutable::now($tz);

        // First bucket start, in the stats timezone.
        $start = $bucket === 1
            ? $now->startOfHour()->subHours($hours - 1)
            : $now->startOfDay()->subDays(intdiv($hours, 24) - 1);

        $base = BoostConnection::query()
            ->where('created_at', '>=', $start->utc())
            ->when($serverId !== null, fn ($q) => $q->where('server_id', $serverId));

        $total = (clone $base)->count();
        $players = (int) (clone $base)
            ->selectRaw("COUNT(DISTINCT COALESCE(steam_id, player_ip, player_name, CONCAT('#', id))) AS c")
            ->value('c');
        $servers = (int) (clone $base)->whereNotNull('server_id')->distinct()->count('server_id');
        $lastAt = (clone $base)->max('created_at');

        // Counts per UTC hour from the database, re-bucketed here (time zone offsets are whole hours).
        $hourly = (clone $base)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS h, COUNT(*) AS c")
            ->groupBy('h')
            ->pluck('c', 'h');

        $series = [];
        $index = [];
        $steps = $bucket === 1 ? $hours : intdiv($hours, 24);
        for ($i = 0; $i < $steps; ++$i) {
            $at = $bucket === 1 ? $start->addHours($i) : $start->addDays($i);
            $key = $bucket === 1 ? $at->format('Y-m-d H') : $at->format('Y-m-d');
            $index[$key] = $i;
            $series[] = [
                'label' => $bucket === 1 ? $at->format('H\h') : $at->format('d/m'),
                'title' => $bucket === 1 ? $at->format('d/m H:00') . '–' . $at->addHour()->format('H:00') : $at->format('d/m/Y'),
                'count' => 0,
            ];
        }
        foreach ($hourly as $h => $c) {
            $local = CarbonImmutable::parse($h, 'UTC')->setTimezone($tz);
            $key = $bucket === 1 ? $local->format('Y-m-d H') : $local->format('Y-m-d');
            if (isset($index[$key])) {
                $series[$index[$key]]['count'] += (int) $c;
            }
        }

        $topRows = (clone $base)
            ->select('server_id', DB::raw('MAX(address) AS address'), DB::raw('COUNT(*) AS c'))
            ->groupBy('server_id')
            ->orderByDesc('c')
            ->limit(10)
            ->get();
        $names = Server::query()->whereIn('id', $topRows->pluck('server_id')->filter())->pluck('name', 'id');
        $top = $topRows->map(fn ($r) => [
            'server_id' => $r->server_id,
            'name' => $r->server_id ? ($names[$r->server_id] ?? "#{$r->server_id}") : 'servidor excluído',
            'address' => (string) $r->address,
            'count' => (int) $r->c,
        ])->all();

        return [
            'total' => $total,
            'players' => $players,
            'servers' => $servers,
            'last' => $lastAt ? CarbonImmutable::parse($lastAt, 'UTC')->setTimezone($tz)->locale('pt_BR') : null,
            'series' => $series,
            'top' => $top,
        ];
    }

    /** @return Collection<int, BoostConnection> */
    public function recent(?int $serverId = null, ?string $search = null, int $limit = 100): Collection
    {
        return BoostConnection::query()
            ->with('server:id,name')
            ->when($serverId !== null, fn ($q) => $q->where('server_id', $serverId))
            ->when($search !== null && $search !== '', function ($q) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $q->where(fn ($w) => $w->where('player_name', 'like', $like)
                    ->orWhere('player_ip', 'like', $like)
                    ->orWhere('steam_id', 'like', $like));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function purge(int $days = self::RETENTION_DAYS): int
    {
        return BoostConnection::query()->where('created_at', '<', now()->subDays($days))->delete();
    }
}
