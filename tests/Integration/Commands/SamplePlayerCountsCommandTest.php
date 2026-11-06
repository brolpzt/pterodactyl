<?php

namespace Pterodactyl\Tests\Integration\Commands;

use Illuminate\Support\Facades\Artisan;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\ServerPlayerSample;
use Pterodactyl\Services\GameQuery\GameQueryService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class SamplePlayerCountsCommandTest extends IntegrationTestCase
{
    public function testCommandSamplesEligibleServersAndPurgesOldRows(): void
    {
        $service = \Mockery::mock(GameQueryService::class);
        $this->app->instance(GameQueryService::class, $service);

        $server = $this->createServerModel(['status' => null]);
        Egg::query()->whereKey($server->egg_id)->update(['gamedig' => 'cs16']);

        ServerPlayerSample::query()->create([
            'server_id' => $server->id,
            'players' => 1,
            'max_players' => 32,
            'online' => true,
            'sampled_at' => now()->subDays(10),
        ]);

        $service->expects('query')->once()->andReturn([
            'online' => true,
            'players' => 8,
            'max_players' => 32,
        ]);

        $exitCode = Artisan::call('p:maintenance:sample-player-counts');

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, ServerPlayerSample::query()->where('server_id', $server->id)->count());
        $this->assertSame(8, (int) ServerPlayerSample::query()->where('server_id', $server->id)->value('players'));
    }

    public function testPurgeOnlyFlagDoesNotSample(): void
    {
        $service = \Mockery::mock(GameQueryService::class);
        $this->app->instance(GameQueryService::class, $service);

        $server = $this->createServerModel(['status' => null]);
        Egg::query()->whereKey($server->egg_id)->update(['gamedig' => 'cs16']);

        ServerPlayerSample::query()->create([
            'server_id' => $server->id,
            'players' => 1,
            'max_players' => 32,
            'online' => true,
            'sampled_at' => now()->subDays(10),
        ]);

        $service->shouldNotReceive('query');

        $exitCode = Artisan::call('p:maintenance:sample-player-counts', ['--purge-only' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, ServerPlayerSample::query()->where('server_id', $server->id)->count());
    }
}
