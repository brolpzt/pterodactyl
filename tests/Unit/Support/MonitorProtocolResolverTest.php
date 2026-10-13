<?php

namespace Pterodactyl\Tests\Unit\Support;

use Mockery;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Support\ServerType;
use Illuminate\Database\Eloquent\Collection;
use Pterodactyl\Support\MonitorProtocolResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Pterodactyl\Tests\TestCase;

class MonitorProtocolResolverTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testResolvesSourceFromCs16(): void
    {
        $server = $this->makeServer(eggId: 1, gamedig: 'cs16', ip: '1.2.3.4', port: 27015);

        $this->assertSame('source', MonitorProtocolResolver::resolve($server));
        $this->assertSame(
            ['address' => '1.2.3.4:27015', 'protocol' => 'source'],
            MonitorProtocolResolver::toMonitorEntry($server)
        );
    }

    public function testResolvesQuake3FromCod2(): void
    {
        $server = $this->makeServer(eggId: 1, gamedig: 'cod2', ip: '1.2.3.4', port: 28960);

        $this->assertSame('quake3', MonitorProtocolResolver::resolve($server));
    }

    public function testResolvesSamp(): void
    {
        $server = $this->makeServer(eggId: 1, gamedig: 'samp', ip: '1.2.3.4', port: 7777);

        $this->assertSame('samp', MonitorProtocolResolver::resolve($server));
    }

    public function testResolvesTeamspeakWithVoiceAndQueryPorts(): void
    {
        $queryVar = new EggVariable([
            'env_variable' => 'QUERY_PORT',
            'default_value' => '10017',
        ]);
        $queryVar->server_value = null;

        $server = $this->makeServer(
            eggId: ServerType::TS3_EGG_ID,
            gamedig: 'teamspeak3',
            ip: '89.39.161.156',
            port: 9120,
            variables: [$queryVar],
        );

        $this->assertSame('teamspeak', MonitorProtocolResolver::resolve($server));
        $this->assertSame(
            ['address' => '89.39.161.156:9120:10017', 'protocol' => 'teamspeak'],
            MonitorProtocolResolver::toMonitorEntry($server)
        );
    }

    public function testOmitsUnsupportedProtocols(): void
    {
        $server = $this->makeServer(eggId: 1, gamedig: 'minecraft', ip: '1.2.3.4', port: 25565);

        $this->assertNull(MonitorProtocolResolver::resolve($server));
        $this->assertNull(MonitorProtocolResolver::toMonitorEntry($server));
    }

    /**
     * @param array<int, EggVariable> $variables
     */
    private function makeServer(
        int $eggId,
        string $gamedig,
        string $ip,
        int $port,
        array $variables = [],
    ): Server {
        $egg = new Egg(['name' => 'Test', 'gamedig' => $gamedig]);
        $egg->id = $eggId;

        $allocation = new Allocation(['ip' => $ip, 'port' => $port]);
        $node = new Node(['fqdn' => 'node.example']);

        /** @var Server&Mockery\MockInterface $server */
        $server = Mockery::mock(Server::class)->makePartial();
        $server->egg_id = $eggId;
        $server->setRelation('egg', $egg);
        $server->setRelation('allocation', $allocation);
        $server->setRelation('node', $node);
        $server->setRelation('variables', new Collection($variables));
        $server->shouldReceive('loadMissing')->andReturnSelf();

        return $server;
    }
}
