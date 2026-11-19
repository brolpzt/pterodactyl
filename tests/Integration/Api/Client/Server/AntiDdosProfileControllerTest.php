<?php

namespace Pterodactyl\Tests\Integration\Api\Client\Server;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\Permission;
use Illuminate\Support\Facades\Bus;
use Pterodactyl\Models\NodeGcoreIp;
use Pterodactyl\Models\EggDdosProfile;
use Pterodactyl\Models\EggDdosSetting;
use Pterodactyl\Tests\Integration\Api\Client\ClientApiIntegrationTestCase;

class AntiDdosProfileControllerTest extends ClientApiIntegrationTestCase
{
    /** @var array{0: EggDdosProfile, 1: EggDdosProfile} */
    private array $profiles;

    private function prepare(Server $server): void
    {
        Bus::fake();
        $egg = Egg::query()->findOrFail($server->egg_id);
        $egg->forceFill(['port_slots' => [
            ['env_variable' => 'SERVER_PORT', 'name' => 'Game Port', 'required' => true, 'gcore_policy' => 'counter-strike-16-gold', 'gcore_proto' => 'udp'],
        ]])->saveQuietly();
        $server->node->forceFill(['gcore_enabled' => true])->saveQuietly();
        $allocation = Allocation::query()->findOrFail($server->allocation_id);
        $allocation->forceFill(['server_id' => $server->id, 'gcore_protected' => true])->save();
        NodeGcoreIp::query()->create(['node_id' => $server->node_id, 'ip' => $allocation->ip]);

        EggDdosSetting::query()->updateOrCreate(['egg_id' => $egg->id], ['enabled' => true, 'cooldown_minutes' => 20]);
        EggDdosProfile::query()->where('egg_id', $egg->id)->delete();
        \Pterodactyl\Models\ServerDdosProfileState::query()->create(['server_id' => $server->id, 'client_locked' => false]);
        $this->profiles = [
            EggDdosProfile::query()->create(['egg_id' => $egg->id, 'policy' => 'counter-strike-16-gold', 'label' => 'Proteção CS', 'alert_type' => 'success', 'sort_order' => 0]),
            EggDdosProfile::query()->create(['egg_id' => $egg->id, 'policy' => 'udp-general', 'label' => 'UDP genérico', 'alert_type' => 'warning', 'alert_message' => 'Menos restritivo', 'sort_order' => 1]),
        ];
    }

    public function testOwnerSeesAndChangesTheProfile(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->prepare($server);

        $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/anti-ddos/profile")
            ->assertOk()
            ->assertJsonPath('attributes.enabled', true)
            ->assertJsonPath('attributes.current.label', 'Proteção CS')
            ->assertJsonPath('attributes.options.1.alert.type', 'warning')
            ->assertJsonMissingPath('attributes.options.0.policy');

        $this->actingAs($user)->postJson("/api/client/servers/{$server->uuid}/anti-ddos/profile", ['profile' => $this->profiles[1]->id])
            ->assertOk()
            ->assertJsonPath('attributes.current.label', 'UDP genérico');

        $this->assertSame('udp-general', Allocation::query()->find($server->allocation_id)->gcore_override_policy);
        $this->assertDatabaseHas('activity_logs', ['event' => 'server:antiddos.profile']);

        // Cooldown.
        $this->actingAs($user)->postJson("/api/client/servers/{$server->uuid}/anti-ddos/profile", ['profile' => $this->profiles[0]->id])
            ->assertStatus(400);
    }

    public function testSubuserNeedsThePermissionToChange(): void
    {
        [$user, $server] = $this->generateTestAccount([Permission::ACTION_WEBSOCKET_CONNECT]);
        $this->prepare($server);

        $this->actingAs($user)->getJson("/api/client/servers/{$server->uuid}/anti-ddos/profile")->assertOk();
        $this->actingAs($user)->postJson("/api/client/servers/{$server->uuid}/anti-ddos/profile", ['profile' => $this->profiles[1]->id])
            ->assertForbidden();

        [$allowed, $server2] = $this->generateTestAccount([Permission::ACTION_ANTIDDOS_PROFILE]);
        $this->prepare($server2);
        $this->actingAs($allowed)->postJson("/api/client/servers/{$server2->uuid}/anti-ddos/profile", ['profile' => $this->profiles[1]->id])
            ->assertOk();
    }

    public function testAdminPagesRenderAndSaveTheEggOptions(): void
    {
        [, $server] = $this->generateTestAccount();
        $this->prepare($server);
        defined('LARAVEL_START') || define('LARAVEL_START', microtime(true));
        $admin = User::factory()->create(['root_admin' => true]);
        $eggId = $server->egg_id;

        $this->actingAs($admin)->get("/admin/nests/egg/{$eggId}/ddos")->assertOk()
            ->assertSee('Troca pelo cliente')
            ->assertSee('UDP genérico');
        $this->actingAs($admin)->get("/admin/servers/view/{$server->id}/ddos")->assertOk()
            ->assertSee('counter-strike-16-gold')
            ->assertSee('Proteção CS');

        // Client on the second option; the admin removes it: the server goes back to the default.
        app(\Pterodactyl\Services\Gcore\DdosProfileService::class)->changeByClient($server, $this->profiles[1]->id);
        $this->assertSame('udp-general', Allocation::query()->find($server->allocation_id)->gcore_override_policy);
        $this->actingAs($admin)->patch("/admin/nests/egg/{$eggId}/ddos", [
            'enabled' => '1',
            'cooldown_minutes' => 25,
            'options' => [
                ['id' => $this->profiles[0]->id, 'policy' => 'counter-strike-16-gold', 'label' => 'Proteção CS 1.6', 'alert_type' => 'info'],
                ['id' => $this->profiles[1]->id, 'delete' => '1', 'policy' => 'udp-general', 'label' => 'UDP genérico', 'alert_type' => 'warning'],
                ['policy' => 'ratelimiter-low', 'label' => 'Limitador', 'alert_type' => 'danger', 'alert_message' => 'Use com cuidado'],
                ['policy' => '', 'label' => ''],
            ],
        ])->assertRedirect();

        $this->assertSame(['Proteção CS 1.6', 'Limitador'], EggDdosProfile::query()->where('egg_id', $eggId)->orderBy('sort_order')->pluck('label')->all());
        $this->assertSame(25, EggDdosSetting::query()->where('egg_id', $eggId)->value('cooldown_minutes'));
        $this->assertNull(Allocation::query()->find($server->allocation_id)->gcore_override_policy);

        // The client name cannot be the Gcore policy name.
        $this->actingAs($admin)->patch("/admin/nests/egg/{$eggId}/ddos", [
            'enabled' => '1',
            'cooldown_minutes' => 25,
            'options' => [['policy' => 'udp-general', 'label' => 'udp-general', 'alert_type' => 'info']],
        ])->assertRedirect();
        $this->assertSame(2, EggDdosProfile::query()->where('egg_id', $eggId)->count());

        // Admin change from the server tab, no cooldown.
        $limiter = EggDdosProfile::query()->where('egg_id', $eggId)->where('label', 'Limitador')->firstOrFail();
        $this->actingAs($admin)->post("/admin/servers/view/{$server->id}/ddos", ['choice' => 'profile:' . $limiter->id])->assertRedirect();
        $this->assertSame('ratelimiter-low', Allocation::query()->find($server->allocation_id)->gcore_override_policy);
    }
}
