<?php

namespace Pterodactyl\Jobs;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\XdpSyncLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Services\Xdp\XdpFirewallSyncService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

/**
 * Pushes a node's XDP port map to Wings a few seconds after its servers change.
 * Unique per node until it starts, so a burst of changes becomes one sync and a
 * change made while a sync runs still queues another one.
 */
class SyncNodeXdpJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    public const DELAY_SECONDS = 15;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(public int $nodeId, public ?string $reason = null)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->nodeId;
    }

    /**
     * Queues a sync for the node when it has XDP enabled.
     */
    public static function dispatchForNode(?int $nodeId, ?string $reason = null): void
    {
        if (!$nodeId) {
            return;
        }

        try {
            if (!Node::query()->whereKey($nodeId)->where('xdp_enabled', true)->exists()) {
                return;
            }
            self::dispatch($nodeId, $reason)->delay(now()->addSeconds(self::DELAY_SECONDS));
        } catch (\Throwable $e) {
            Log::warning('XDP: could not queue node sync.', ['node_id' => $nodeId, 'error' => $e->getMessage()]);
        }
    }

    public function handle(XdpFirewallSyncService $service): void
    {
        $node = Node::query()->find($this->nodeId);
        if (!$node || !$node->xdp_enabled) {
            return;
        }

        try {
            $service->syncNode($node, false, XdpSyncLog::TRIGGER_AUTOMATIC, $this->reason);
        } catch (\Throwable $e) {
            Log::warning('XDP: node sync failed.', ['node_id' => $this->nodeId, 'error' => $e->getMessage()]);
        }
    }
}
