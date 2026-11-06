<?php

namespace Pterodactyl\Services\Servers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayerSample;

class PlayerStatsService
{
    public function __construct(private PlayerCountSamplingService $samplingService)
    {
    }

    /**
     * Build the client API payload for historical player stats.
     *
     * @return array{
     *     supported: bool,
     *     days: int,
     *     samples: list<array{sampled_at: string, players: int, max_players: int, online: bool}>,
     *     summary: array{
     *         current: int|null,
     *         peak: int|null,
     *         average: float|null,
     *         busiest_hour: array{hour: int, average_players: float}|null
     *     }
     * }
     */
    public function forServer(Server $server, int $days = PlayerCountSamplingService::RETENTION_DAYS): array
    {
        $days = max(1, min(PlayerCountSamplingService::RETENTION_DAYS, $days));
        $supported = $this->samplingService->supportsPlayerStats($server) || $this->hasAnySamples($server);

        $since = Carbon::now()->subDays($days);
        $samples = ServerPlayerSample::query()
            ->where('server_id', $server->id)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'players', 'max_players', 'online']);

        return [
            'supported' => $supported,
            'days' => $days,
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
        $byHour = $samples
            ->groupBy(static fn (ServerPlayerSample $sample) => (int) $sample->sampled_at->timezone(config('app.timezone'))->format('G'))
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
