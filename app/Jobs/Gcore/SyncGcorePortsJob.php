<?php

namespace Pterodactyl\Jobs\Gcore;

use Pterodactyl\Models\Node;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\GcoreSyncRun;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Services\Gcore\GcoreFirewallSyncService;

/**
 * Port sync of one Gcore-protected IP (egg policies + linked rules). Idempotent: it reads the
 * panel and the profile when it runs, so a retry or a duplicate run only finds nothing to do.
 */
class SyncGcorePortsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Below the redis retry_after (90s) so a slow Gcore call is not picked up twice. */
    public int $timeout = 85;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $runId)
    {
    }

    public function handle(GcoreFirewallSyncService $sync): void
    {
        $run = GcoreSyncRun::query()->find($this->runId);
        if ($run === null || !$run->isPending()) {
            return;
        }
        $run->forceFill(['status' => 'running', 'started_at' => now(), 'attempts' => $run->attempts + 1])->save();

        $node = Node::query()->find($run->node_id);
        if ($node === null) {
            $run->forceFill(['status' => 'error', 'summary' => 'Node não existe mais.', 'finished_at' => now()])->save();

            return;
        }

        try {
            $summary = $sync->syncIp($node->fresh(['gcoreIps']), (string) $run->ip, force: $run->force);
        } catch (\Throwable $e) {
            $last = $this->attempts() >= $this->tries;
            $run->forceFill([
                'status' => $last ? 'error' : 'queued',
                'summary' => $e->getMessage() . ($last ? '' : ' (nova tentativa em instantes)'),
                'finished_at' => $last ? now() : null,
            ])->save();
            Log::warning('Gcore port sync failed.', ['run' => $run->id, 'ip' => $run->ip, 'error' => $e->getMessage()]);
            if (!$last) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 120);
            }

            return;
        }

        $run->forceFill(['status' => 'ok', 'summary' => $summary, 'finished_at' => now()])->save();
    }

    public function failed(?\Throwable $e): void
    {
        GcoreSyncRun::query()->whereKey($this->runId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'error',
            'summary' => 'Job interrompido: ' . ($e?->getMessage() ?? 'tempo esgotado'),
            'finished_at' => now(),
        ]);
    }
}
