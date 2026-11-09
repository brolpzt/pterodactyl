<?php

namespace Pterodactyl\Tests\Integration\Api\Client;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subuser;
use Pterodactyl\Models\PanelAlert;
use Illuminate\Support\Facades\Notification;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class PanelAlertControllerTest extends ClientApiIntegrationTestCase
{
    protected function tearDown(): void
    {
        PanelAlert::query()->delete();

        parent::tearDown();
    }

    private function alert(array $attributes = []): PanelAlert
    {
        return PanelAlert::query()->create(array_merge([
            'message' => 'Aviso',
            'placements' => ['dashboard', 'account', 'server', 'admin'],
        ], $attributes));
    }

    private function ids(string $url, User $user): array
    {
        $response = $this->actingAs($user)->getJson($url);
        $response->assertOk();

        return array_column(array_column($response->json('data'), 'attributes'), 'id');
    }

    private function engineOnDisk(array|\Throwable $result, ?int $times = 1): void
    {
        $this->mock(DaemonFileRepository::class, function ($mock) use ($result, $times) {
            $mock->shouldReceive('setServer')->andReturnSelf();
            $expectation = $mock->shouldReceive('getDirectory')->with('/');
            $times === null ? $expectation->never() : $expectation->times($times);
            $result instanceof \Throwable ? $expectation->andThrow($result) : $expectation->andReturn($result);
        });
    }

    private function engineFile(int $size): array
    {
        return [['name' => 'engine_i486.so', 'size' => $size, 'file' => true, 'directory' => false]];
    }

    private function hgHldsAlert(array $attributes = []): PanelAlert
    {
        return $this->alert(array_merge(['server_condition' => PanelAlert::CONDITION_HGHLDS_MISSING], $attributes));
    }

    public function testConditionShowsTheAlertOnlyWithoutTheHgHldsEngine(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $alert = $this->hgHldsAlert();

        $this->engineOnDisk($this->engineFile(1327992));

        $url = "/api/client/servers/{$server->uuid}/alerts";
        $this->assertSame([$alert->id], $this->ids($url, $user));
        // The node is asked once: the expectation above fails on a second listing.
        $this->assertSame([$alert->id], $this->ids($url, $user));
    }

    public function testConditionHidesTheAlertWhenTheEngineIsPresent(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->hgHldsAlert();

        $this->engineOnDisk($this->engineFile(1363824));

        $this->assertSame([], $this->ids("/api/client/servers/{$server->uuid}/alerts", $user));
    }

    /**
     * @dataProvider undeterminedProvider
     */
    public function testConditionStaysHiddenWhenTheEngineCannotBeChecked(string $case): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->hgHldsAlert();

        $this->engineOnDisk($case === 'node offline'
            ? new DaemonConnectionException(new \GuzzleHttp\Exception\TransferException('down'))
            : [['name' => 'server.jar', 'size' => 10, 'file' => true, 'directory' => false]]);

        $this->assertSame([], $this->ids("/api/client/servers/{$server->uuid}/alerts", $user));
    }

    public static function undeterminedProvider(): array
    {
        return [['node offline'], ['no engine file']];
    }

    public function testConditionNeverAsksTheNodeForUsersOutsideTheAudience(): void
    {
        [$user, $server] = $this->generateTestAccount();
        $this->hgHldsAlert(['audience' => 'admins']);

        $this->engineOnDisk([], null);

        $this->assertSame([], $this->ids("/api/client/servers/{$server->uuid}/alerts", $user));
    }

    public function testConditionalAlertsReachAdminsOfTheServerPage(): void
    {
        [, $server] = $this->generateTestAccount();
        $admin = User::factory()->create(['root_admin' => true]);
        $alert = $this->hgHldsAlert(['audience' => 'admins']);

        $this->engineOnDisk($this->engineFile(1327992));

        $this->assertSame([$alert->id], $this->ids("/api/client/servers/{$server->uuid}/alerts", $admin));
    }

    public function testConditionalAlertsStayOffAccountPages(): void
    {
        [$user] = $this->generateTestAccount();
        $this->hgHldsAlert();

        $this->engineOnDisk([], null);

        $this->assertSame([], $this->ids('/api/client/alerts', $user));
    }

    public function testUntargetedAlertReachesEveryUser(): void
    {
        $alert = $this->alert(['type' => 'danger']);

        $response = $this->actingAs(User::factory()->create())->getJson('/api/client/alerts');

        $response->assertOk();
        $response->assertJsonPath('data.0.object', PanelAlert::RESOURCE_NAME);
        $response->assertJsonPath('data.0.attributes.id', $alert->id);
        $response->assertJsonPath('data.0.attributes.type', 'danger');
    }

    public function testDashboardAlertRequiresAccessToAMatchingServer(): void
    {
        Notification::fake();

        $server = $this->createServerModel();
        $stranger = User::factory()->create();
        $subuser = User::factory()->create();
        Subuser::query()->create(['user_id' => $subuser->id, 'server_id' => $server->id, 'permissions' => []]);

        $byEgg = $this->alert(['egg_ids' => [$server->egg_id]]);
        $byNest = $this->alert(['nest_ids' => [$server->nest_id]]);
        $otherEgg = $this->alert(['egg_ids' => [$server->egg_id + 1000]]);

        $this->assertEqualsCanonicalizing([$byEgg->id, $byNest->id], $this->ids('/api/client/alerts', $server->user));
        $this->assertEqualsCanonicalizing([$byEgg->id, $byNest->id], $this->ids('/api/client/alerts', $subuser));
        $this->assertSame([], $this->ids('/api/client/alerts', $stranger));
        $this->assertNotContains($otherEgg->id, $this->ids('/api/client/alerts', $server->user));
    }

    public function testServerFiltersCombine(): void
    {
        $server = $this->createServerModel();
        $location = $server->node->location_id;
        $url = "/api/client/servers/{$server->uuid}/alerts";

        $eggOrNest = $this->alert(['nest_ids' => [$server->nest_id + 1000], 'egg_ids' => [$server->egg_id]]);
        $matchesAll = $this->alert(['egg_ids' => [$server->egg_id], 'node_ids' => [$server->node_id], 'location_ids' => [$location], 'server_ids' => [$server->id]]);
        $wrongNode = $this->alert(['egg_ids' => [$server->egg_id], 'node_ids' => [$server->node_id + 1000]]);
        $wrongLocation = $this->alert(['location_ids' => [$location + 1000]]);
        $wrongServer = $this->alert(['server_ids' => [$server->id + 1000]]);

        $ids = $this->ids($url, $server->user);

        $this->assertEqualsCanonicalizing([$eggOrNest->id, $matchesAll->id], $ids);
        $this->assertNotContains($wrongNode->id, $ids);
        $this->assertNotContains($wrongLocation->id, $ids);
        $this->assertNotContains($wrongServer->id, $ids);
    }

    public function testPlacementAudienceAndScheduleAreRespected(): void
    {
        $server = $this->createServerModel();
        $admin = User::factory()->create(['root_admin' => true]);

        $dashboardOnly = $this->alert(['placements' => ['dashboard']]);
        $serverOnly = $this->alert(['placements' => ['server']]);
        $adminOnlyPlacement = $this->alert(['placements' => ['admin']]);
        $adminsAudience = $this->alert(['audience' => 'admins']);
        $this->alert(['enabled' => false]);
        $this->alert(['starts_at' => now()->addHour()]);
        $this->alert(['ends_at' => now()->subHour()]);
        $running = $this->alert(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);

        $this->assertEqualsCanonicalizing(
            [$dashboardOnly->id, $running->id],
            $this->ids('/api/client/alerts', $server->user)
        );
        $this->assertEqualsCanonicalizing(
            [$serverOnly->id, $running->id],
            $this->ids("/api/client/servers/{$server->uuid}/alerts", $server->user)
        );
        $this->assertEqualsCanonicalizing(
            [$dashboardOnly->id, $adminsAudience->id, $running->id],
            $this->ids('/api/client/alerts', $admin)
        );
        $this->assertNotContains($adminOnlyPlacement->id, $this->ids('/api/client/alerts', $admin));
    }

    public function testAlertsStayAvailableWhileServerIsSuspended(): void
    {
        $server = $this->createServerModel(['status' => Server::STATUS_SUSPENDED]);
        $alert = $this->alert();

        $this->assertSame([$alert->id], $this->ids("/api/client/servers/{$server->uuid}/alerts", $server->user));
    }

    public function testServerAlertsAreHiddenFromUsersWithoutAccess(): void
    {
        $server = $this->createServerModel();
        $this->alert();

        $this->actingAs(User::factory()->create())
            ->getJson("/api/client/servers/{$server->uuid}/alerts")
            ->assertNotFound();
    }

    public function testMessageIsRenderedSafely(): void
    {
        $alert = $this->alert([
            'message' => "**forte** [site](https://example.com) <script>alert(1)</script> [x](javascript:alert(1))\n<img src=x onerror=alert(1)>",
        ]);

        $html = $alert->renderedMessage();

        $this->assertStringContainsString('<strong>forte</strong>', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }
}
