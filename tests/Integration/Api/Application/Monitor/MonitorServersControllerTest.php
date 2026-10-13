<?php

namespace Pterodactyl\Tests\Integration\Api\Application\Monitor;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Support\ServerType;
use Pterodactyl\Services\Acl\Api\AdminAcl;
use Pterodactyl\Tests\Integration\Api\Application\ApplicationApiIntegrationTestCase;

class MonitorServersControllerTest extends ApplicationApiIntegrationTestCase
{
    public function testForbiddenWithoutServersReadPermission(): void
    {
        $this->createNewDefaultApiKey($this->getApiUser(), ['r_servers' => AdminAcl::NONE]);

        $this->assertAccessDeniedJson(
            $this->getJson('/api/application/monitor/servers')
        );
    }

    public function testListsServersInMonitorFormat(): void
    {
        $source = $this->createServerWithGamedig('cs16', [
            'ip' => '89.39.161.172',
            'port' => 27015,
        ]);

        $quake3 = $this->createServerWithGamedig('cod2', [
            'ip' => '89.39.161.172',
            'port' => 28960,
        ]);

        $samp = $this->createServerWithGamedig('samp', [
            'ip' => '89.39.161.172',
            'port' => 7780,
        ]);

        $ts3 = $this->createTs3Server([
            'ip' => '89.39.161.156',
            'port' => 9120,
            'query_port' => 10017,
        ]);

        // Minecraft e suspensos devem ser omitidos.
        $this->createServerWithGamedig('minecraft', [
            'ip' => '10.0.0.1',
            'port' => 25565,
        ]);
        $this->createServerWithGamedig('cs16', [
            'ip' => '10.0.0.2',
            'port' => 27016,
            'status' => Server::STATUS_SUSPENDED,
        ]);

        $response = $this->getJson('/api/application/monitor/servers');

        $response->assertOk();
        $response->assertJsonStructure(['servers' => [['address', 'protocol']]]);

        $servers = $response->json('servers');
        $this->assertContains([
            'address' => sprintf('%s:%d', $source->allocation->alias, $source->allocation->port),
            'protocol' => 'source',
        ], $servers);
        $this->assertContains([
            'address' => sprintf('%s:%d', $quake3->allocation->alias, $quake3->allocation->port),
            'protocol' => 'quake3',
        ], $servers);
        $this->assertContains([
            'address' => sprintf('%s:%d', $samp->allocation->alias, $samp->allocation->port),
            'protocol' => 'samp',
        ], $servers);
        $this->assertContains([
            'address' => sprintf('%s:%d:%d', $ts3->allocation->alias, $ts3->allocation->port, 10017),
            'protocol' => 'teamspeak',
        ], $servers);

        foreach ($servers as $entry) {
            $this->assertStringNotContainsString('10.0.0.1:25565', $entry['address']);
            $this->assertStringNotContainsString('10.0.0.2:27016', $entry['address']);
        }
    }

    /**
     * @param array{ip: string, port: int, status?: string|null} $allocation
     */
    private function createServerWithGamedig(string $gamedig, array $allocation): Server
    {
        $base = Egg::query()
            ->where('author', 'support@pterodactyl.io')
            ->where('name', 'Bungeecord')
            ->firstOrFail();

        $egg = $base->replicate(['id', 'uuid']);
        $egg->uuid = Uuid::uuid4()->toString();
        $egg->name = 'Monitor ' . $gamedig;
        $egg->gamedig = $gamedig;
        $egg->save();

        $attributes = [
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'status' => $allocation['status'] ?? null,
        ];

        $server = $this->createServerModel($attributes);
        $server->allocation->forceFill([
            'ip' => $allocation['ip'],
            'port' => $allocation['port'],
            'ip_alias' => null,
        ])->save();

        return $server->fresh(['egg', 'allocation', 'node']);
    }

    /**
     * @param array{ip: string, port: int, query_port: int} $allocation
     */
    private function createTs3Server(array $allocation): Server
    {
        $egg = Egg::query()->find(ServerType::TS3_EGG_ID);

        if ($egg === null) {
            $base = Egg::query()
                ->where('author', 'support@pterodactyl.io')
                ->where('name', 'Bungeecord')
                ->firstOrFail();

            $egg = $base->replicate(['id', 'uuid']);
            $egg->id = ServerType::TS3_EGG_ID;
            $egg->uuid = Uuid::uuid4()->toString();
            $egg->name = 'TeamSpeak 3';
            $egg->gamedig = 'teamspeak3';
            $egg->save();
        } else {
            $egg->forceFill(['gamedig' => 'teamspeak3'])->save();
        }

        EggVariable::query()->updateOrCreate(
            [
                'egg_id' => $egg->id,
                'env_variable' => 'QUERY_PORT',
            ],
            [
                'name' => 'Query Port',
                'description' => 'TS3 query port',
                'default_value' => (string) $allocation['query_port'],
                'user_viewable' => false,
                'user_editable' => false,
                'rules' => 'required|integer',
            ]
        );

        $server = $this->createServerModel([
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'status' => null,
        ]);

        $server->allocation->forceFill([
            'ip' => $allocation['ip'],
            'port' => $allocation['port'],
            'ip_alias' => null,
        ])->save();

        return $server->fresh(['egg', 'allocation', 'node', 'variables']);
    }
}
