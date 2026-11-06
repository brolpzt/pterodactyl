<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayerSample;
use Pterodactyl\Support\GameDigTypeResolver;
use Pterodactyl\Support\ServerType;
use Pterodactyl\Services\GameQuery\GameQueryService;

class PlayerCountSamplingService
{
    public const RETENTION_DAYS = 7;

    public function __construct(private GameQueryService $gameQueryService)
    {
    }

    /**
     * Sample player counts for all eligible servers.
     *
     * @return array{sampled: int, failed: int, skipped: int}
     */
    public function sampleAll(int $chunkSize = 50): array
    {
        $sampled = 0;
        $failed = 0;
        $skipped = 0;

        $this->eligibleServersQuery()
            ->chunkById($chunkSize, function ($servers) use (&$sampled, &$failed, &$skipped) {
                foreach ($servers as $server) {
                    if (!$this->isEligible($server)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        $this->sampleServer($server);
                        $sampled++;
                    } catch (\Throwable $exception) {
                        $failed++;
                        Log::warning('Failed to sample player count for server', [
                            'server_id' => $server->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return compact('sampled', 'failed', 'skipped');
    }

    public function sampleServer(Server $server): ServerPlayerSample
    {
        try {
            $result = $this->gameQueryService->query($server);

            return ServerPlayerSample::query()->create([
                'server_id' => $server->id,
                'players' => max(0, (int) ($result['players'] ?? 0)),
                'max_players' => max(0, (int) ($result['max_players'] ?? 0)),
                'online' => (bool) ($result['online'] ?? false),
                'sampled_at' => now(),
            ]);
        } catch (\Throwable) {
            return ServerPlayerSample::query()->create([
                'server_id' => $server->id,
                'players' => 0,
                'max_players' => 0,
                'online' => false,
                'sampled_at' => now(),
            ]);
        }
    }

    public function purgeOlderThanDays(int $days = self::RETENTION_DAYS): int
    {
        return ServerPlayerSample::query()
            ->where('sampled_at', '<', now()->subDays($days))
            ->delete();
    }

    public function supportsPlayerStats(Server $server): bool
    {
        if (ServerType::isTs3($server)) {
            return false;
        }

        $server->loadMissing('egg');

        return GameDigTypeResolver::resolve(
            $server->egg->gamedig ?? null,
            $server->egg->name ?? null
        ) !== null;
    }

    public function isEligible(Server $server): bool
    {
        return !$server->isSuspended() && $this->supportsPlayerStats($server);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\Pterodactyl\Models\Server>
     */
    private function eligibleServersQuery()
    {
        return Server::query()
            ->with(['egg', 'allocation', 'variables', 'allocations'])
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', Server::STATUS_SUSPENDED);
            })
            ->where('egg_id', '!=', ServerType::TS3_EGG_ID)
            ->whereHas('egg', function ($query) {
                $query->whereNotNull('gamedig')->where('gamedig', '!=', '');
            });
    }
}
