<?php

namespace Pterodactyl\Observers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Jobs\SyncNodeXdpJob;

/**
 * Port slots or the game type of an egg decide the XDP profile of its servers' ports:
 * refresh every node that hosts servers of the egg when they change.
 */
class EggXdpObserver
{
    public function updated(Egg $egg): void
    {
        if (!$egg->wasChanged(['port_slots', 'gamedig', 'name'])) {
            return;
        }

        $nodeIds = Server::query()->where('egg_id', $egg->id)->distinct()->pluck('node_id');
        foreach ($nodeIds as $nodeId) {
            SyncNodeXdpJob::dispatchForNode((int) $nodeId);
        }
    }
}
