<?php

namespace Pterodactyl\Tests\Integration\Services\Allocations;

use Pterodactyl\Models\Allocation;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Pterodactyl\Services\Allocations\FindAssignableAllocationService;
use Pterodactyl\Exceptions\Service\Allocation\AutoAllocationNotEnabledException;
use Pterodactyl\Exceptions\Service\Allocation\NoAutoAllocationSpaceAvailableException;

class FindAssignableAllocationServiceTest extends IntegrationTestCase
{
    /**
     * Setup tests.
     */
    public function setUp(): void
    {
        parent::setUp();

        config()->set('pterodactyl.client_features.allocations.enabled', true);
        config()->set('pterodactyl.client_features.allocations.range_start', 0);
        config()->set('pterodactyl.client_features.allocations.range_end', 0);
    }

    /**
     * Test that an unassigned allocation is preferred rather than creating an entirely new
     * allocation for the server.
     */
    public function testExistingAllocationIsPreferred()
    {
        $server = $this->createServerModel();

        $created = Allocation::factory()->create([
            'node_id' => $server->node_id,
            'ip' => $server->allocation->ip,
        ]);

        $response = $this->getService()->handle($server);

        $this->assertSame($created->id, $response->id);
        $this->assertSame($server->allocation->ip, $response->ip);
        $this->assertSame($server->node_id, $response->node_id);
        $this->assertSame($server->id, $response->server_id);
        $this->assertNotSame($server->allocation_id, $response->id);
    }

    /**
     * With an egg port step, free ports below the primary port must not overflow the
     * unsigned `port` column (MOD(port - primary) raised SQLSTATE 22003 in MySQL).
     */
    public function testStepFilterHandlesPortsBelowThePrimary()
    {
        $server = $this->createServerModel();
        $server->allocation->forceFill(['port' => 27019])->save();
        $egg = $server->egg;
        $previous = $egg->only(['port_range_start', 'port_range_end', 'port_step']);
        $egg->forceFill(['port_range_start' => 27015, 'port_range_end' => null, 'port_step' => 2])->save();

        try {
            foreach ([27016, 27017, 27018, 27021] as $port) {
                Allocation::factory()->create(['node_id' => $server->node_id, 'ip' => $server->allocation->ip, 'port' => $port]);
            }

            $response = $this->getService()->handle($server->fresh());

            $this->assertSame(27017, $response->port);
            $this->assertSame($server->id, $response->server_id);
        } finally {
            // This test case has no transaction; other tests share the egg.
            $egg->forceFill($previous)->save();
        }
    }

    /**
     * Test that a new allocation is created if there is not a free one available.
     */
    public function testNewAllocationIsCreatedIfOneIsNotFound()
    {
        $server = $this->createServerModel();
        config()->set('pterodactyl.client_features.allocations.range_start', 5000);
        config()->set('pterodactyl.client_features.allocations.range_end', 5005);

        $response = $this->getService()->handle($server);
        $this->assertSame($server->id, $response->server_id);
        $this->assertSame($server->allocation->ip, $response->ip);
        $this->assertSame($server->node_id, $response->node_id);
        $this->assertNotSame($server->allocation_id, $response->id);
        $this->assertTrue($response->port >= 5000 && $response->port <= 5005);
    }

    /**
     * Test that a currently assigned port is never assigned to a server.
     */
    public function testOnlyPortNotInUseIsCreated()
    {
        $server = $this->createServerModel();
        $server2 = $this->createServerModel(['node_id' => $server->node_id]);

        config()->set('pterodactyl.client_features.allocations.range_start', 5000);
        config()->set('pterodactyl.client_features.allocations.range_end', 5001);

        Allocation::factory()->create([
            'server_id' => $server2->id,
            'node_id' => $server->node_id,
            'ip' => $server->allocation->ip,
            'port' => 5000,
        ]);

        $response = $this->getService()->handle($server);
        $this->assertSame(5001, $response->port);
    }

    public function testExceptionIsThrownIfNoMoreAllocationsCanBeCreatedInRange()
    {
        $server = $this->createServerModel();
        $server2 = $this->createServerModel(['node_id' => $server->node_id]);
        config()->set('pterodactyl.client_features.allocations.range_start', 5000);
        config()->set('pterodactyl.client_features.allocations.range_end', 5005);

        for ($i = 5000; $i <= 5005; ++$i) {
            Allocation::factory()->create([
                'ip' => $server->allocation->ip,
                'port' => $i,
                'node_id' => $server->node_id,
                'server_id' => $server2->id,
            ]);
        }

        $this->expectException(NoAutoAllocationSpaceAvailableException::class);
        $this->expectExceptionMessage('Cannot assign additional allocation: no more space available on node.');

        $this->getService()->handle($server);
    }

    /**
     * Test that we only auto-allocate from the current server's IP address space, and not a random
     * IP address available on that node.
     */
    public function testExceptionIsThrownIfOnlyFreePortIsOnADifferentIp()
    {
        $server = $this->createServerModel();

        Allocation::factory()->times(5)->create(['node_id' => $server->node_id]);

        $this->expectException(NoAutoAllocationSpaceAvailableException::class);
        $this->expectExceptionMessage('Cannot assign additional allocation: no more space available on node.');

        $this->getService()->handle($server);
    }

    public function testExceptionIsThrownIfStartOrEndRangeIsNotDefined()
    {
        $server = $this->createServerModel();

        $this->expectException(NoAutoAllocationSpaceAvailableException::class);
        $this->expectExceptionMessage('Cannot assign additional allocation: no more space available on node.');

        $this->getService()->handle($server);
    }

    public function testExceptionIsThrownIfStartOrEndRangeIsNotNumeric()
    {
        $server = $this->createServerModel();
        config()->set('pterodactyl.client_features.allocations.range_start', 'hodor');
        config()->set('pterodactyl.client_features.allocations.range_end', 10);

        try {
            $this->getService()->handle($server);
            $this->fail('This assertion should not be reached.');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
            $this->assertSame('Expected an integerish value. Got: string', $exception->getMessage());
        }

        config()->set('pterodactyl.client_features.allocations.range_start', 10);
        config()->set('pterodactyl.client_features.allocations.range_end', 'hodor');

        try {
            $this->getService()->handle($server);
            $this->fail('This assertion should not be reached.');
        } catch (\Exception $exception) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
            $this->assertSame('Expected an integerish value. Got: string', $exception->getMessage());
        }
    }

    public function testExceptionIsThrownIfFeatureIsNotEnabled()
    {
        config()->set('pterodactyl.client_features.allocations.enabled', false);
        $server = $this->createServerModel();

        $this->expectException(AutoAllocationNotEnabledException::class);

        $this->getService()->handle($server);
    }

    private function getService(): FindAssignableAllocationService
    {
        return $this->app->make(FindAssignableAllocationService::class);
    }
}
