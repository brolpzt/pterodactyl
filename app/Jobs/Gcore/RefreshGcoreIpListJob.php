<?php

namespace Pterodactyl\Jobs\Gcore;

use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreSyncRun;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListRefreshService;

/**
 * Download one IP list and push the change to the Gcore rules that use it. Failed Gcore pushes
 * are already retried by the 15-minute maintenance command, so this job runs once.
 */
class RefreshGcoreIpListJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 85;

    public function __construct(public readonly int $runId)
    {
    }

    public function handle(GcoreIpListRefreshService $refresh): void
    {
        $run = GcoreSyncRun::query()->find($this->runId);
        if ($run === null || !$run->isPending()) {
            return;
        }
        $run->forceFill(['status' => 'running', 'started_at' => now(), 'attempts' => $run->attempts + 1])->save();

        $list = GcoreIpList::query()->find($run->ip_list_id);
        if ($list === null) {
            $run->forceFill(['status' => 'error', 'summary' => 'Lista não existe mais.', 'finished_at' => now()])->save();

            return;
        }

        try {
            $log = $refresh->refresh($list, $run->mode === 'force' ? 'force' : 'manual');
        } catch (\Throwable $e) {
            $run->forceFill(['status' => 'error', 'summary' => $e->getMessage(), 'finished_at' => now()])->save();

            return;
        }

        $run->forceFill([
            'status' => match ($log->status) {
                'changed', 'unchanged' => 'ok',
                'rejected' => 'warning',
                default => 'error',
            },
            'summary' => $list->name . ': ' . $log->message,
            'finished_at' => now(),
        ])->save();
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
