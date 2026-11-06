<?php

namespace Pterodactyl\Tests\Unit\Services\GameQuery;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Support\GameDigTypeResolver;
use Pterodactyl\Services\GameQuery\GameQueryService;
use Pterodactyl\Services\GameQuery\PlutoniumMasterList;
use Pterodactyl\Repositories\Wings\DaemonGameQueryRepository;
use Pterodactyl\Exceptions\Service\GameQuery\GameQueryException;

class PlutoniumQueryTest extends TestCase
{
    private const ENTRY = [
        'ip' => '80.75.221.133', 'port' => 5006, 'game' => 't6mp',
        'hostname' => '^1FFA & GUN GAME ^2BRASIL', 'map' => 'mp_hijacked', 'gametype' => 'gun',
        'players' => [['username' => 'LukazcomTwo', 'id' => 7171271, 'ping' => 34], ['username' => 'Outro', 'id' => 2, 'ping' => 80]],
        'maxplayers' => 18, 'bots' => 2, 'password' => false, 'hardcore' => false, 'revision' => 5369,
    ];

    public function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function testResolverMapsPlutoniumAliases(): void
    {
        foreach (['plutonium', 'Plutonium T6', 't6', 't6zm', 'iw5mp'] as $gamedig) {
            $this->assertSame('plutonium', GameDigTypeResolver::resolve($gamedig), $gamedig);
        }

        // Existing types keep going through Wings.
        $this->assertSame('t4', GameDigTypeResolver::resolve('t4'));
    }

    public function testMasterListIsFetchedOnceAndMatchedByIpAndPort(): void
    {
        Http::fake([PlutoniumMasterList::URL => Http::response([self::ENTRY, ['ip' => '1.1.1.1', 'port' => 5006]])]);

        $list = new PlutoniumMasterList();
        $this->assertSame('t6mp', $list->find(['10.0.0.1', '80.75.221.133'], 5006)['game']);
        $this->assertNull($list->find(['80.75.221.133'], 5007));
        $this->assertNull($list->find(['10.0.0.1'], 5006));

        Http::assertSentCount(1);
    }

    public function testMasterListErrorsBecomeQueryErrors(): void
    {
        Http::fake([PlutoniumMasterList::URL => Http::response('down', 503)]);

        $this->expectException(GameQueryException::class);
        (new PlutoniumMasterList())->find(['80.75.221.133'], 5006);
    }

    public function testServiceReadsPlutoniumServersFromTheList(): void
    {
        Http::fake([PlutoniumMasterList::URL => Http::response([self::ENTRY])]);

        $result = $this->service()->query($this->server(alias: '80.75.221.133', port: 5006));

        $this->assertTrue($result['online']);
        $this->assertSame('plutonium', $result['type']);
        $this->assertSame('80.75.221.133', $result['address']);
        $this->assertSame('mp_hijacked', $result['map']);
        $this->assertSame('t6mp', $result['game']);
        $this->assertSame('gun', $result['gametype']);
        $this->assertSame(2, $result['players']);
        $this->assertSame(2, $result['bots']);
        $this->assertSame(18, $result['max_players']);
        $this->assertSame('r5369', $result['version']);
        $this->assertSame([['name' => 'LukazcomTwo', 'ping' => 34], ['name' => 'Outro', 'ping' => 80]], $result['player_list']);
    }

    public function testExtraIpsAndMissingServers(): void
    {
        Http::fake([PlutoniumMasterList::URL => Http::response([self::ENTRY])]);

        // Not listed under the allocation address → offline.
        $this->assertFalse($this->service()->query($this->server(alias: '89.39.161.172', port: 5006))['online']);

        // ...unless the egress IP is configured.
        config()->set('services.plutonium.extra_ips', '9.9.9.9, 80.75.221.133');
        $this->assertTrue($this->service()->query($this->server(alias: '89.39.161.172', port: 5006))['online']);
    }

    private function service(): GameQueryService
    {
        $daemon = \Mockery::mock(DaemonGameQueryRepository::class);
        $daemon->shouldNotReceive('setServer');

        return new GameQueryService($daemon, new PlutoniumMasterList());
    }

    private function server(string $alias, int $port): Server
    {
        $server = \Mockery::mock(Server::class)->makePartial();
        $server->shouldReceive('loadMissing')->andReturnSelf();
        // Slot/branding resolvers look up variables; none configured here.
        $relation = \Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
        $relation->shouldReceive('where', 'orderByDesc', 'whereIn', 'orWhere', 'with')->andReturnSelf();
        $relation->shouldReceive('first')->andReturnNull();
        $relation->shouldReceive('get')->andReturn(collect());
        $server->shouldReceive('variables')->andReturn($relation);

        $egg = new Egg();
        $egg->forceFill(['name' => 'Plutonium T6', 'gamedig' => 'plutonium']);
        $node = new Node();
        $node->forceFill(['fqdn' => '10.255.255.1']);
        $allocation = new Allocation();
        $allocation->forceFill(['ip' => '172.16.0.10', 'ip_alias' => $alias, 'port' => $port]);

        $server->setRelation('egg', $egg);
        $server->setRelation('node', $node);
        $server->setRelation('allocation', $allocation);
        $server->setRelation('variables', collect());

        return $server;
    }
}
