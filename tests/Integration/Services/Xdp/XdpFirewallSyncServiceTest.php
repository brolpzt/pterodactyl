<?php

namespace Pterodactyl\Tests\Integration\Services\Xdp;

use Mockery as m;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Jobs\SyncNodeXdpJob;
use Illuminate\Support\Facades\Queue;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Xdp\XdpFirewallSyncService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pterodactyl\Repositories\Wings\DaemonXdpRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

class XdpFirewallSyncServiceTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private Egg $egg;

    public function setUp(): void
    {
        parent::setUp();

        $this->egg = Egg::query()->firstOrFail();
        $this->egg->forceFill([
            'gamedig' => 'ets2',
            'port_slots' => [
                ['env_variable' => 'SERVER_PORT', 'name' => 'Game Port', 'required' => true],
                ['env_variable' => 'QUERY_PORT', 'name' => 'Query Port', 'required' => true, 'gcore_proto' => 'udp'],
                ['env_variable' => 'RCON_PORT', 'name' => 'RCON', 'required' => false, 'gcore_proto' => 'tcp'],
                ['env_variable' => 'VOICE_PORT', 'name' => 'Voice', 'required' => false, 'xdp_profile' => 'generic'],
                ['env_variable' => 'WEB_PORT', 'name' => 'Web', 'required' => false, 'xdp_profile' => 'none'],
            ],
        ])->saveQuietly();
    }

    /** Server with a primary plus one allocation per extra slot (port_env set). */
    private function server(Node $node, int $base): Server
    {
        $primary = Allocation::factory()->create(['node_id' => $node->id, 'port' => $base]);
        $server = $this->createServerModel(['egg_id' => $this->egg->id, 'node_id' => $node->id, 'allocation_id' => $primary->id]);
        $primary->update(['server_id' => $server->id]);
        foreach (['QUERY_PORT' => 1, 'RCON_PORT' => 2, 'VOICE_PORT' => 3, 'WEB_PORT' => 4, null => 5] as $env => $offset) {
            Allocation::factory()->create([
                'node_id' => $node->id,
                'server_id' => $server->id,
                'port' => $base + $offset,
                'port_env' => $env === '' ? null : $env,
            ]);
        }

        return $server;
    }

    public function testDesiredMapFollowsTheEggSlots(): void
    {
        $node = Node::factory()->create(['location_id' => $this->createServerModel()->location->id]);
        $this->server($node, 30000);
        // Unassigned allocations never go to the node.
        Allocation::factory()->create(['node_id' => $node->id, 'port' => 31000]);

        $desired = app(XdpFirewallSyncService::class)->desiredPortsDetailed($node);

        $this->assertSame([
            30000 => 'source', // primary: automatic from the egg (ets2)
            30001 => 'source', // UDP slot: automatic
            30003 => 'generic', // slot override
            30005 => 'source', // no slot: follows SERVER_PORT
        ], $desired['ports']);
        $this->assertSame([], $desired['conflicts']);
        $this->assertSame('Query Port', $desired['rows'][1]['slot']);
    }

    public function testSyncSendsTheMapAndDryRunWorksWhileDisabled(): void
    {
        $node = Node::factory()->create(['location_id' => $this->createServerModel()->location->id]);
        $this->server($node, 30000);

        $repository = m::mock(DaemonXdpRepository::class);
        $repository->allows('setNode')->andReturnSelf();
        $repository->expects('sync')->with(m::on(fn ($ports) => array_keys($ports) === [30000, 30001, 30003, 30005]), true)
            ->andReturn(['dry_run' => true, 'added' => [['port' => 30000, 'to' => 'source']], 'updated' => [], 'removed' => [['port' => 9999, 'from' => 'cs16']], 'unchanged' => 3]);
        $repository->expects('sync')->with(m::type('array'), false)
            ->andReturn(['dry_run' => false, 'added' => [], 'updated' => [], 'removed' => [], 'unchanged' => 4]);
        $this->app->instance(DaemonXdpRepository::class, $repository);
        $service = app(XdpFirewallSyncService::class);

        $preview = $service->syncNode($node, dryRun: true);
        $this->assertCount(1, $preview['removed']);
        $this->assertNull($service->lastSyncStatus($node));

        try {
            $service->syncNode($node);
            $this->fail('Sync must require XDP enabled on the node.');
        } catch (DisplayException) {
        }

        $node->update(['xdp_enabled' => true]);
        $this->assertSame(4, $service->syncNode($node)['unchanged']);
        $this->assertTrue($service->lastSyncStatus($node)['ok']);
    }

    public function testWingsServerSyncQueuesTheNodeOnlyWhenXdpIsEnabled(): void
    {
        Queue::fake();
        $server = $this->createServerModel();
        $repository = m::mock(DaemonServerRepository::class)->makePartial();
        $repository->allows('getHttpClient')->andReturn(m::mock(\GuzzleHttp\Client::class, fn ($c) => $c->allows('post')));
        $repository->setServer($server);

        $repository->sync();
        Queue::assertNothingPushed();

        $server->node->update(['xdp_enabled' => true]);
        $repository->sync();
        Queue::assertPushed(SyncNodeXdpJob::class, fn (SyncNodeXdpJob $job) => $job->nodeId === $server->node_id);
    }

    public function testAdminPageShowsThePreviewAndTogglesTheNode(): void
    {
        $node = Node::factory()->create(['location_id' => $this->createServerModel()->location->id]);
        $this->server($node, 30000);
        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        $admin = \Pterodactyl\Models\User::factory()->create(['root_admin' => true]);

        $repository = m::mock(DaemonXdpRepository::class);
        $repository->allows('setNode')->andReturnSelf();
        $repository->allows('sync')->andReturn(['dry_run' => true, 'added' => [], 'updated' => [], 'removed' => [['port' => 9999, 'from' => 'cs16']], 'unchanged' => 4]);
        $this->app->instance(DaemonXdpRepository::class, $repository);

        $this->actingAs($admin)->get("/admin/nodes/view/{$node->id}/xdp")->assertOk()
            ->assertSee('Firewall XDP')
            ->assertSee('9999')
            ->assertSee('Query Port');

        $this->actingAs($admin)->post("/admin/nodes/view/{$node->id}/xdp", ['xdp_enabled' => 1])
            ->assertRedirect("/admin/nodes/view/{$node->id}/xdp");
        $this->assertTrue($node->fresh()->xdp_enabled);
    }
}
