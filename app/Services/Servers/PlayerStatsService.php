<?php

namespace Pterodactyl\Services\Servers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayerSample;
use Pterodactyl\Support\TimePeriod;

class PlayerStatsService
{
    /** @var list<string> */
    public const PERIODS = ['15m', '1h', '6h', '24h', '7d'];

    public function __construct(private PlayerCountSamplingService $samplingService)
    {
    }

    /**
     * Build the client API payload for historical player stats.
     *
     * @return array{
     *     supported: bool,
     *     period: string,
     *     samples: list<array{sampled_at: string, players: int, max_players: int, online: bool}>,
     *     summary: array{
     *         current: int|null,
     *         peak: int|null,
     *         average: float|null,
     *         busiest_hour: array{hour: int, average_players: float}|null
     *     }
     * }
     */
    public function forServer(Server $server, ?string $period = '7d'): array
    {
        $period = TimePeriod::normalize($period, self::PERIODS, '7d');
        // Hard cap to sampling retention.
        if (TimePeriod::seconds($period) > TimePeriod::seconds('7d')) {
            $period = '7d';
        }

        $supported = $this->samplingService->supportsPlayerStats($server) || $this->hasAnySamples($server);
        $since = TimePeriod::since($period);

        $samples = ServerPlayerSample::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'players', 'max_players', 'online']);

        return [
            'supported' => $supported,
            'period' => $period,
            'samples' => $samples->map(static fn (ServerPlayerSample $sample) => [
                'sampled_at' => $sample->sampled_at->toAtomString(),
                'players' => (int) $sample->players,
                'max_players' => (int) $sample->max_players,
                'online' => (bool) $sample->online,
            ])->values()->all(),
            'summary' => $this->buildSummary($samples),
        ];
    }

    /**
     * @param Collection<int, ServerPlayerSample> $samples
     *
     * @return array{
     *     current: int|null,
     *     peak: int|null,
     *     average: float|null,
     *     busiest_hour: array{hour: int, average_players: float}|null
     * }
     */
    private function buildSummary(Collection $samples): array
    {
        if ($samples->isEmpty()) {
            return [
                'current' => null,
                'peak' => null,
                'average' => null,
                'busiest_hour' => null,
            ];
        }

        $playerCounts = $samples->pluck('players');

        return [
            'current' => (int) $samples->last()->players,
            'peak' => (int) $playerCounts->max(),
            'average' => round((float) $playerCounts->avg(), 2),
            'busiest_hour' => $this->resolveBusiestHour($samples),
        ];
    }

    /**
     * @param Collection<int, ServerPlayerSample> $samples
     *
     * @return array{hour: int, average_players: float}|null
     */
    private function resolveBusiestHour(Collection $samples): ?array
    {
        // Prefer Brazil local time for HostGamer ops; APP_TIMEZONE is UTC in production.
        $timezone = config('app.stats_timezone', 'America/Sao_Paulo');
        $byHour = $samples
            ->groupBy(static fn (ServerPlayerSample $sample) => (int) $sample->sampled_at->timezone($timezone)->format('G'))
            ->map(static fn (Collection $group) => round((float) $group->avg('players'), 2));

        if ($byHour->isEmpty()) {
            return null;
        }

        $hour = (int) $byHour->search($byHour->max());

        return [
            'hour' => $hour,
            'average_players' => (float) $byHour->get($hour),
        ];
    }

    private function hasAnySamples(Server $server): bool
    {
        return ServerPlayerSample::query()->where('server_id', $server->id)->exists();
    }
}
