<?php

namespace Pterodactyl\Services\Gcore;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreSyncRun;
use Pterodactyl\Jobs\Gcore\SyncGcorePortsJob;
use Pterodactyl\Jobs\Gcore\RefreshGcoreIpListJob;

/**
 * Puts Gcore work on the queue. A run still waiting for a worker absorbs new requests for the
 * same target (the job reads the panel state when it starts, so it covers them too).
 */
class GcoreSyncQueue
{
    public function ports(Node $node, string $ip, string $reason, bool $force = false): GcoreSyncRun
    {
        $this->prune();
        $waiting = GcoreSyncRun::query()
            ->where('kind', GcoreSyncRun::KIND_PORTS)
            ->where('ip', $ip)
            ->where('status', 'queued')
            ->latest('id')
            ->first();
        if ($waiting !== null) {
            return $this->absorb($waiting, $reason, $force);
        }

        $run = GcoreSyncRun::query()->create([
            'kind' => GcoreSyncRun::KIND_PORTS,
            'node_id' => $node->id,
            'ip' => $ip,
            'force' => $force,
            'label' => 'Portas ' . $ip,
            'reasons' => [$reason],
        ]);
        SyncGcorePortsJob::dispatch($run->id)->afterCommit();

        return $run;
    }

    /** @param 'manual'|'force' $mode */
    public function ipList(GcoreIpList $list, string $mode, string $reason): GcoreSyncRun
    {
        $this->prune();
        $waiting = GcoreSyncRun::query()
            ->where('kind', GcoreSyncRun::KIND_IP_LIST)
            ->where('ip_list_id', $list->id)
            ->where('status', 'queued')
            ->latest('id')
            ->first();
        if ($waiting !== null) {
            if ($mode === 'force') {
                $waiting->mode = 'force';
            }

            return $this->absorb($waiting, $reason, false);
        }

        $run = GcoreSyncRun::query()->create([
            'kind' => GcoreSyncRun::KIND_IP_LIST,
            'ip_list_id' => $list->id,
            'mode' => $mode,
            'label' => 'Lista ' . $list->name,
            'reasons' => [$reason],
        ]);
        RefreshGcoreIpListJob::dispatch($run->id)->afterCommit();

        return $run;
    }

    private function absorb(GcoreSyncRun $run, string $reason, bool $force): GcoreSyncRun
    {
        $reasons = $run->reasons ?? [];
        if (!in_array($reason, $reasons, true) && count($reasons) < 20) {
            $reasons[] = $reason;
        }
        $run->forceFill(['reasons' => $reasons, 'force' => $run->force || $force])->save();

        return $run;
    }

    private function prune(): void
    {
        GcoreSyncRun::query()->where('created_at', '<', now()->subDays(30))->delete();
    }
}
