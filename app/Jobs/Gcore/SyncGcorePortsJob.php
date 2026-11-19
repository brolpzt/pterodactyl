<?php

namespace Pterodactyl\Jobs\Gcore;

use Pterodactyl\Models\Node;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\GcoreSyncRun;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Services\Gcore\GcoreWriteLock;
use Pterodactyl\Services\Gcore\GcoreFirewallSyncService;

/**
 * Port sync of one Gcore-protected IP (egg policies + linked rules). Idempotent: it reads the
 * panel and the profile when it runs, so a retry or a duplicate run only finds nothing to do.
 * Only one Gcore write runs at a time: while another one runs, the job waits on the queue.
 */
class SyncGcorePortsJob implements ShouldQueue
{
    use Queueable;

    /** Real attempts (Gcore calls); waits for the write lock do not count. */
    public const MAX_ATTEMPTS = 3;

    /** Seconds before checking the write lock again. */
    public const BUSY_DELAY = 10;

    /** Below the redis retry_after (90s) so a slow Gcore call is not picked up twice. */
    public int $timeout = 85;

    public array $backoff = [30, 120];

    public function __construct(public readonly int $runId)
    {
    }

    /** Waiting for the write lock releases the job many times: bound it by time, not tries. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHour();
    }

    public function handle(GcoreFirewallSyncService $sync): void
    {
        $run = GcoreSyncRun::query()->find($this->runId);
        if ($run === null || !$run->isPending()) {
            return;
        }
        [$ran] = GcoreWriteLock::attempt(fn () => $this->sync($run, $sync));
        if (!$ran) {
            // Another Gcore write is running: stay queued and come back shortly.
            $this->release(self::BUSY_DELAY);
        }
    }

    private function sync(GcoreSyncRun $run, GcoreFirewallSyncService $sync): void
    {
        $run->forceFill(['status' => 'running', 'started_at' => now(), 'attempts' => $run->attempts + 1])->save();

        $node = Node::query()->find($run->node_id);
        if ($node === null) {
            $run->forceFill(['status' => 'error', 'summary' => 'Node não existe mais.', 'finished_at' => now()])->save();

            return;
        }

        try {
            $summary = $sync->syncIp($node->fresh(['gcoreIps']), (string) $run->ip, force: $run->force);
        } catch (\Throwable $e) {
            $last = $run->attempts >= self::MAX_ATTEMPTS;
            $run->forceFill([
                'status' => $last ? 'error' : 'queued',
                'summary' => $e->getMessage() . ($last ? '' : ' (nova tentativa em instantes)'),
                'finished_at' => $last ? now() : null,
            ])->save();
            Log::warning('Gcore port sync failed.', ['run' => $run->id, 'ip' => $run->ip, 'error' => $e->getMessage()]);
            if (!$last) {
                $this->release($this->backoff[$run->attempts - 1] ?? 120);
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
