<?php

namespace Pterodactyl\Tests\Integration\Services\Servers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerPlayerSample;
use Pterodactyl\Services\GameQuery\GameQueryService;
use Pterodactyl\Services\Servers\PlayerCountSamplingService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;

class PlayerCountSamplingServiceTest extends IntegrationTestCase
{
    public function testSampleServerStoresOnlinePlayerCount(): void
    {
        $service = \Mockery::mock(GameQueryService::class);
        $this->app->instance(GameQueryService::class, $service);

        $server = $this->createServerModel(['status' => null]);
        Egg::query()->whereKey($server->egg_id)->update(['gamedig' => 'cs16']);
        $server->refresh()->load('egg');

        $service->expects('query')->once()->with(\Mockery::on(fn ($value) => $value->id === $server->id))->andReturn([
            'online' => true,
            'players' => 12,
            'max_players' => 32,
        ]);

        $sample = $this->app->make(PlayerCountSamplingService::class)->sampleServer($server);

        $this->assertTrue($sample->online);
        $this->assertSame(12, $sample->players);
        $this->assertSame(32, $sample->max_players);
        $this->assertSame(1, ServerPlayerSample::query()->where('server_id', $server->id)->count());
    }

    public function testSampleServerStoresOfflineOnQueryFailure(): void
    {
        $service = \Mockery::mock(GameQueryService::class);
        $this->app->instance(GameQueryService::class, $service);

        $server = $this->createServerModel(['status' => null]);
        Egg::query()->whereKey($server->egg_id)->update(['gamedig' => 'cs16']);
        $server->refresh()->load('egg');

        $service->expects('query')->once()->andThrow(new \RuntimeException('daemon down'));

        $sample = $this->app->make(PlayerCountSamplingService::class)->sampleServer($server);

        $this->assertFalse($sample->online);
        $this->assertSame(0, $sample->players);
        $this->assertSame(0, $sample->max_players);
    }

    public function testPurgeRemovesSamplesOlderThanRetention(): void
    {
        $server = $this->createServerModel(['status' => null]);

        ServerPlayerSample::query()->create([
            'server_id' => $server->id,
            'players' => 5,
            'max_players' => 32,
            'online' => true,
            'sampled_at' => now()->subDays(8),
        ]);
        ServerPlayerSample::query()->create([
            'server_id' => $server->id,
            'players' => 3,
            'max_players' => 32,
            'online' => true,
            'sampled_at' => now()->subDays(2),
        ]);

        $deleted = $this->app->make(PlayerCountSamplingService::class)->purgeOlderThanDays(7);

        $this->assertSame(1, $deleted);
        $this->assertSame(1, ServerPlayerSample::query()->where('server_id', $server->id)->count());
        $this->assertSame(3, (int) ServerPlayerSample::query()->where('server_id', $server->id)->value('players'));
    }

    public function testSuspendedServersAreNotEligible(): void
    {
        $server = $this->createServerModel(['status' => Server::STATUS_SUSPENDED]);
        Egg::query()->whereKey($server->egg_id)->update(['gamedig' => 'cs16']);
        $server->refresh()->load('egg');

        $sampling = $this->app->make(PlayerCountSamplingService::class);

        $this->assertTrue($sampling->supportsPlayerStats($server));
        $this->assertFalse($sampling->isEligible($server));
    }
}
