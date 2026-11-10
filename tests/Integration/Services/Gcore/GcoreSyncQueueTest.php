<?php

namespace Pterodactyl\Tests\Integration\Services\Gcore;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\NodeGcoreIp;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\GcoreSyncRun;
use Illuminate\Support\Facades\Queue;
use Pterodactyl\Jobs\Gcore\SyncGcorePortsJob;
use Pterodactyl\Services\Gcore\GcoreSyncQueue;
use Pterodactyl\Jobs\Gcore\RefreshGcoreIpListJob;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pterodactyl\Services\Gcore\GcoreFirewallSyncService;

class GcoreSyncQueueTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private Node $node;

    public function setUp(): void
    {
        parent::setUp();

        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        $this->withoutMiddleware(\Pterodactyl\Http\Middleware\VerifyCsrfToken::class);
        $this->node = Node::factory()->create(['location_id' => Location::factory()->create()->id, 'gcore_enabled' => true]);
        NodeGcoreIp::query()->create(['node_id' => $this->node->id, 'ip' => '203.0.113.10']);
        NodeGcoreIp::query()->create(['node_id' => $this->node->id, 'ip' => '203.0.113.11']);
    }

    public function testNodeButtonQueuesOneRunPerIpAndWaitingRunsAbsorbRequests(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['root_admin' => true]);

        $this->actingAs($admin)->post("/admin/nodes/view/{$this->node->id}/allocation/gcore-sync")
            ->assertRedirect()
            ->assertSessionHas('alert_messages', fn ($a) => str_contains(implode(' ', $a['success'] ?? []), 'na fila para 2 IP(s)'));
        Queue::assertPushed(SyncGcorePortsJob::class, 2);

        // A second request while the run still waits is merged into it (no extra job).
        app(GcoreSyncQueue::class)->ports($this->node, '203.0.113.10', 'servidor criado: X (#1)', force: true);
        Queue::assertPushed(SyncGcorePortsJob::class, 2);
        $run = GcoreSyncRun::query()->where('ip', '203.0.113.10')->sole();
        $this->assertSame('queued', $run->status);
        $this->assertTrue($run->force);
        $this->assertCount(2, $run->reasons);
        $this->assertStringContainsString('servidor criado', $run->reasons[1]);

        // Once a worker took it, a new request gets its own run.
        $run->forceFill(['status' => 'running'])->save();
        app(GcoreSyncQueue::class)->ports($this->node, '203.0.113.10', 'outro');
        Queue::assertPushed(SyncGcorePortsJob::class, 3);
    }

    public function testPortsJobRecordsResultAndErrors(): void
    {
        Queue::fake();
        $run = app(GcoreSyncQueue::class)->ports($this->node, '203.0.113.10', 'teste', force: true);

        $sync = \Mockery::mock(GcoreFirewallSyncService::class);
        $sync->expects('syncIp')->with(\Mockery::type(Node::class), '203.0.113.10', true)->andReturn("IP 203.0.113.10 · perfil 1 · sem alterações\nResultado: ok");
        (new SyncGcorePortsJob($run->id))->handle($sync);
        $run->refresh();
        $this->assertSame('ok', $run->status);
        $this->assertSame(1, $run->attempts);
        $this->assertStringContainsString('sem alterações', $run->summary);
        $this->assertNotNull($run->finished_at);

        // Finished runs are never executed again (duplicate delivery).
        (new SyncGcorePortsJob($run->id))->handle(\Mockery::mock(GcoreFirewallSyncService::class));

        $failing = app(GcoreSyncQueue::class)->ports($this->node, '203.0.113.11', 'teste');
        $sync = \Mockery::mock(GcoreFirewallSyncService::class);
        $sync->expects('syncIp')->andThrow(new \RuntimeException('API HTTP 502'));
        $job = new SyncGcorePortsJob($failing->id);
        $job->tries = 1;
        $job->handle($sync);
        $failing->refresh();
        $this->assertSame('error', $failing->status);
        $this->assertSame('API HTTP 502', $failing->summary);

        $html = $this->actingAs(User::factory()->create(['root_admin' => true]))
            ->getJson('/admin/gcore/live/runs?ip=203.0.113.11')->assertOk();
        $this->assertStringContainsString('API HTTP 502', $html->json('html'));
        $this->assertFalse($html->json('pending'));
    }

    public function testIpListRefreshRunsOnTheQueue(): void
    {
        config()->set('queue.default', 'sync');
        Http::fake(['lists.example.test/*' => Http::response("173.245.48.0/20\n")]);
        $admin = User::factory()->create(['root_admin' => true]);

        $this->actingAs($admin)->post('/admin/gcore/ip-lists', [
            'name' => 'CF', 'url' => 'https://lists.example.test/cf.txt', 'refresh_hours' => 24, 'enabled' => '1',
        ])->assertRedirect()
            ->assertSessionHas('alert_messages', fn ($a) => str_contains(implode(' ', $a['info'] ?? []), 'Lista CF: CF: +1 / -0'));

        $list = GcoreIpList::query()->where('name', 'CF')->sole();
        $this->assertSame(['173.245.48.0/20'], $list->entryList());
        $run = GcoreSyncRun::query()->where('ip_list_id', $list->id)->sole();
        $this->assertSame('ok', $run->status);

        Queue::fake();
        $this->actingAs($admin)->post("/admin/gcore/ip-lists/{$list->id}/refresh")->assertRedirect()
            ->assertSessionHas('alert_messages', fn ($a) => str_contains(implode(' ', $a['info'] ?? []), 'na fila'));
        Queue::assertPushed(RefreshGcoreIpListJob::class, 1);
        $this->actingAs($admin)->get("/admin/gcore/ip-lists/{$list->id}")->assertOk()->assertSee('na fila');
    }
}
