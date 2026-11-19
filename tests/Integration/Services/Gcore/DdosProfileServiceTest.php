<?php

namespace Pterodactyl\Tests\Integration\Services\Gcore;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Illuminate\Support\Facades\Bus;
use Pterodactyl\Models\NodeGcoreIp;
use Pterodactyl\Models\GcoreSyncRun;
use Pterodactyl\Models\EggDdosProfile;
use Pterodactyl\Models\EggDdosSetting;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Gcore\DdosProfileService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DdosProfileServiceTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private Egg $egg;
    private Server $server;
    private Allocation $allocation;
    private EggDdosProfile $gold;
    private EggDdosProfile $general;

    public function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        $this->egg = Egg::query()->firstOrFail();
        $this->egg->forceFill([
            'port_slots' => [
                ['env_variable' => 'SERVER_PORT', 'name' => 'Game Port', 'required' => true, 'gcore_policy' => 'counter-strike-16-gold', 'gcore_proto' => 'udp'],
            ],
        ])->saveQuietly();

        $server = $this->createServerModel(['egg_id' => $this->egg->id]);
        $node = $server->node;
        $node->forceFill(['gcore_enabled' => true])->saveQuietly();
        $this->allocation = Allocation::query()->findOrFail($server->allocation_id);
        $this->allocation->forceFill(['server_id' => $server->id, 'gcore_protected' => true])->save();
        NodeGcoreIp::query()->create(['node_id' => $node->id, 'ip' => $this->allocation->ip]);
        $this->server = $server->fresh();

        EggDdosSetting::query()->create(['egg_id' => $this->egg->id, 'enabled' => true, 'cooldown_minutes' => 30]);
        $this->gold = EggDdosProfile::query()->create([
            'egg_id' => $this->egg->id, 'policy' => 'counter-strike-16-gold', 'label' => 'Proteção CS 1.6',
            'alert_type' => 'success', 'alert_message' => 'Recomendado. <script>x</script> **forte**', 'sort_order' => 0,
        ]);
        $this->general = EggDdosProfile::query()->create([
            'egg_id' => $this->egg->id, 'policy' => 'udp-general', 'label' => 'Proteção UDP genérica',
            'alert_type' => 'warning', 'alert_title' => 'Menos restritivo', 'sort_order' => 1,
        ]);

        // Hidden by default: the admin shows the picker.
        $this->assertTrue($this->service()->clientPayload($this->server)['locked']);
        $this->service()->setClientLocked($this->server, false);
    }

    private function service(): DdosProfileService
    {
        return $this->app->make(DdosProfileService::class);
    }

    public function testClientPayloadHidesGcorePolicyNames(): void
    {
        $payload = $this->service()->clientPayload($this->server);

        $this->assertTrue($payload['enabled']);
        $this->assertSame($this->gold->id, $payload['current']['id']);
        $this->assertSame(['Proteção CS 1.6', 'Proteção UDP genérica'], array_column($payload['options'], 'label'));
        $this->assertStringNotContainsString('counter-strike', json_encode($payload));
        $this->assertStringNotContainsString('udp-general', json_encode($payload));
        // The notice is rendered like panel alerts: HTML escaped, Markdown applied.
        $this->assertStringContainsString('&lt;script&gt;', $payload['current']['alert']['html']);
        $this->assertStringContainsString('<strong>forte</strong>', $payload['current']['alert']['html']);
    }

    public function testClientChangeSetsOverrideQueuesSyncAndStartsCooldown(): void
    {
        $this->service()->changeByClient($this->server, $this->general->id);

        $allocation = $this->allocation->fresh();
        $this->assertSame('udp-general', $allocation->gcore_override_policy);
        $this->assertSame('udp', $allocation->gcore_override_proto);
        $this->assertSame($this->server->id, $allocation->gcore_override_server_id);
        $this->assertSame(1, GcoreSyncRun::query()->where('ip', $allocation->ip)->count());

        $payload = $this->service()->clientPayload($this->server);
        $this->assertSame($this->general->id, $payload['current']['id']);
        $this->assertNotNull($payload['next_change_at']);

        // Cooldown: going back right away is refused.
        try {
            $this->service()->changeByClient($this->server, $this->gold->id);
            $this->fail('cooldown not enforced');
        } catch (DisplayException $e) {
            $this->assertStringContainsString('novamente em', $e->getMessage());
        }

        // After the cooldown, picking the egg default clears the override.
        $this->travel(31)->minutes();
        $this->service()->changeByClient($this->server, $this->gold->id);
        $this->assertNull($this->allocation->fresh()->gcore_override_policy);
    }

    public function testAdminLockAndCustomPolicyBlockTheClient(): void
    {
        $this->service()->setClientLocked($this->server, true);
        $this->assertTrue($this->service()->clientPayload($this->server)['locked']);
        try {
            $this->service()->changeByClient($this->server, $this->general->id);
            $this->fail('lock not enforced');
        } catch (DisplayException $e) {
            $this->assertStringContainsString('bloqueada', $e->getMessage());
        }
        $this->service()->setClientLocked($this->server, false);

        // A policy outside the options shows as custom and blocks the client.
        $this->service()->changeByAdmin($this->server, null, 'DROP');
        $payload = $this->service()->clientPayload($this->server);
        $this->assertTrue($payload['custom']);
        $this->assertNull($payload['current']['id']);
        $this->assertStringNotContainsString('DROP', json_encode($payload));
        $this->expectException(DisplayException::class);
        $this->service()->changeByClient($this->server, $this->general->id);
    }

    public function testAdminHasNoCooldownAndCanResetTheClients(): void
    {
        $this->service()->changeByClient($this->server, $this->general->id);
        $this->service()->changeByAdmin($this->server, $this->gold->id, null);
        $this->service()->changeByAdmin($this->server, $this->general->id, null);
        $this->assertSame('udp-general', $this->allocation->fresh()->gcore_override_policy);

        $this->service()->resetCooldown($this->server);
        $this->service()->changeByClient($this->server, $this->gold->id);
        $this->assertNull($this->allocation->fresh()->gcore_override_policy);
    }

    public function testDisablingOrRemovingAnOptionRevertsServersButKeepsHandPinnedOverrides(): void
    {
        $this->service()->changeByClient($this->server, $this->general->id);

        $reverted = $this->service()->revertEggServers($this->egg, 'teste', [$this->general->id]);
        $this->assertSame(1, $reverted);
        $this->assertNull($this->allocation->fresh()->gcore_override_policy);

        // A policy pinned by hand (no option) is not touched.
        $this->service()->changeByAdmin($this->server, null, 'DROP');
        $this->assertSame(0, $this->service()->revertEggServers($this->egg, 'teste'));
        $this->assertSame('DROP', $this->allocation->fresh()->gcore_override_policy);
    }

    public function testRepointingAnOptionMovesItsServers(): void
    {
        $this->service()->changeByClient($this->server, $this->general->id);

        $this->general->forceFill(['policy' => 'ratelimiter-low'])->save();
        $this->assertSame(1, $this->service()->repointProfile($this->general, 'udp-general', 'teste'));
        $this->assertSame('ratelimiter-low', $this->allocation->fresh()->gcore_override_policy);
    }

    public function testUnprotectedPortIsNotAvailable(): void
    {
        $this->allocation->forceFill(['gcore_protected' => false])->save();

        $this->assertFalse($this->service()->clientPayload($this->server->fresh())['enabled']);
        $this->expectException(DisplayException::class);
        $this->service()->changeByClient($this->server->fresh(), $this->general->id);
    }
}
