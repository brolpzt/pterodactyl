<?php

namespace Pterodactyl\Tests\Integration\Services\GameQuery;

use Mockery as m;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Services\GameQuery\GameQueryService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Pterodactyl\Repositories\Wings\DaemonGameQueryRepository;

class GameQueryServiceTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private ?int $queriedPort = null;

    public function setUp(): void
    {
        parent::setUp();

        $repository = m::mock(DaemonGameQueryRepository::class);
        $repository->allows('setServer')->andReturnSelf();
        $repository->allows('query')->andReturnUsing(function (string $type, int $port) {
            $this->queriedPort = $port;

            return ['online' => true, 'query_port' => $port, 'max_players' => 8];
        });
        $this->app->instance(DaemonGameQueryRepository::class, $repository);
    }

    /** ETS2-like server: primary game port plus a second allocation, QUERY_PORT variable. */
    private function truckServer(?string $queryPort): array
    {
        $egg = Egg::query()->firstOrFail();
        $egg->forceFill(['gamedig' => 'ets2'])->save();
        $variable = EggVariable::query()->updateOrCreate(
            ['egg_id' => $egg->id, 'env_variable' => 'QUERY_PORT'],
            ['name' => 'Query Port', 'description' => '', 'default_value' => '', 'user_viewable' => true, 'user_editable' => false, 'rules' => 'nullable|integer'],
        );

        $server = $this->createServerModel(['egg_id' => $egg->id]);
        $server->allocation->update(['port' => 27037]);
        $other = Allocation::factory()->create(['node_id' => $server->node_id, 'server_id' => $server->id, 'port' => 27039]);
        if ($queryPort !== null) {
            DB::table('server_variables')->insert(['server_id' => $server->id, 'variable_id' => $variable->id, 'variable_value' => $queryPort]);
        }

        return [$server, $other];
    }

    private function queryPort(Server $server): int
    {
        // Fresh model, as the controllers receive it: the service loads its own relations.
        app(GameQueryService::class)->query(Server::query()->findOrFail($server->id));

        return $this->queriedPort;
    }

    public function testUsesTheQueryPortVariable(): void
    {
        [$server] = $this->truckServer('27039');

        $this->assertSame(27039, $this->queryPort($server));
    }

    public function testQuerySlotOnThePrimaryAllocationDoesNotOverrideTheVariable(): void
    {
        [$server] = $this->truckServer('27039');
        // The primary allocation never feeds an extra-port env (EnvironmentService skips it).
        $server->allocation->update(['port_env' => 'QUERY_PORT']);

        $this->assertSame(27039, $this->queryPort($server));
    }

    public function testQuerySlotOnAnotherAllocationWinsLikeTheContainerEnvironment(): void
    {
        [$server, $other] = $this->truckServer('27041');
        $other->update(['port_env' => 'QUERY_PORT']);

        $this->assertSame(27039, $this->queryPort($server));
    }

    public function testFallsBackToTheGamePortWithoutQueryPort(): void
    {
        [$server] = $this->truckServer(null);

        $this->assertSame(27037, $this->queryPort($server));
    }
}
