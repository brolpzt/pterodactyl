<?php

namespace Pterodactyl\Services\Allocations;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Exceptions\Service\Allocation\AutoAllocationNotEnabledException;
use Pterodactyl\Exceptions\Service\Allocation\NoAutoAllocationSpaceAvailableException;

class FindAssignableAllocationService
{
    /**
     * FindAssignableAllocationService constructor.
     */
    public function __construct(
        private AssignmentService $service,
        private PortSlotSyncService $portSlotSync,
    ) {
    }

    /**
     * Finds an existing unassigned allocation and attempts to assign it to the given server. If
     * no allocation can be found, a new one will be created with a random port between the defined
     * range from the configuration (respecting the egg port range/step when set).
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\CidrOutOfRangeException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\InvalidPortMappingException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\PortOutOfRangeException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\TooManyPortsInRangeException
     */
    public function handle(Server $server): Allocation
    {
        if (!config('pterodactyl.client_features.allocations.enabled')) {
            throw new AutoAllocationNotEnabledException();
        }

        $server->loadMissing(['egg', 'allocation', 'node']);
        $primaryPort = (int) ($server->allocation?->port ?? 0);

        $query = $server->node->allocations()
            ->lockForUpdate()
            ->where('ip', $server->allocation->ip)
            ->whereNull('server_id')
            ->orderBy('port');

        if ($primaryPort > 0) {
            $this->portSlotSync->constrainQueryToExtraSlot($query, $server->egg, $primaryPort);
        }

        /** @var Allocation|null $allocation */
        $allocation = $query->first();

        $allocation = $allocation ?? $this->createNewAllocation($server);

        $allocation->update(['server_id' => $server->id]);

        return $allocation->refresh();
    }

    /**
     * Create a new allocation on the server's node with a port matching the egg range/step
     * (or the panel client_features range as fallback).
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\CidrOutOfRangeException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\InvalidPortMappingException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\PortOutOfRangeException
     * @throws \Pterodactyl\Exceptions\Service\Allocation\TooManyPortsInRangeException
     */
    protected function createNewAllocation(Server $server): Allocation
    {
        $server->loadMissing(['egg', 'allocation', 'node']);
        $primaryPort = (int) ($server->allocation?->port ?? 0);

        if ($this->portSlotSync->hasPortRange($server->egg)) {
            $start = (int) $server->egg->port_range_start;
            $end = (int) $server->egg->port_range_end;
        } else {
            $start = config('pterodactyl.client_features.allocations.range_start', null);
            $end = config('pterodactyl.client_features.allocations.range_end', null);
        }

        if (!$start || !$end) {
            throw new NoAutoAllocationSpaceAvailableException();
        }

        Assert::integerish($start);
        Assert::integerish($end);

        $ports = $server->node->allocations()
            ->where('ip', $server->allocation->ip)
            ->whereBetween('port', [$start, $end])
            ->pluck('port');

        $available = array_values(array_diff(range((int) $start, (int) $end), $ports->toArray()));

        if ($primaryPort > 0) {
            $available = array_values(array_filter(
                $available,
                fn (int $port) => $this->portSlotSync->isValidExtraPort($server->egg, $primaryPort, $port)
            ));
        } elseif ($this->portSlotSync->hasPortRange($server->egg)) {
            $pool = $this->portSlotSync->primaryPoolPorts($server->egg) ?? [];
            $available = array_values(array_intersect($available, $pool));
        }

        if (empty($available)) {
            throw new NoAutoAllocationSpaceAvailableException();
        }

        /** @var int $port */
        $port = $available[array_rand($available)];

        $this->service->handle($server->node, [
            'allocation_ip' => $server->allocation->ip,
            'allocation_ports' => [$port],
        ]);

        /** @var Allocation $allocation */
        $allocation = $server->node->allocations()
            ->lockForUpdate()
            ->where('ip', $server->allocation->ip)
            ->where('port', $port)
            ->firstOrFail();

        return $allocation;
    }
}
