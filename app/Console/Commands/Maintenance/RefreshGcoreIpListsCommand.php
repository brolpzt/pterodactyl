<?php

namespace Pterodactyl\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListBindingService;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListRefreshService;

class RefreshGcoreIpListsCommand extends Command
{
    protected $signature = 'p:maintenance:refresh-gcore-ip-lists
                            {--list= : Only this list id (ignores the refresh interval)}
                            {--force : Accept a list that shrank past the safety limit}
                            {--purge-only : Only delete old log entries}';

    protected $description = 'Download due Gcore IP lists and push changes to the bound ACL rules.';

    public function handle(GcoreIpListRefreshService $refresh, GcoreIpListBindingService $bindings): int
    {
        if ($this->option('purge-only')) {
            $this->line('Gcore IP list logs purged: ' . $refresh->purgeLogs());

            return self::SUCCESS;
        }

        if ($id = $this->option('list')) {
            $list = GcoreIpList::query()->findOrFail((int) $id);
            $logs = [$refresh->refresh($list, $this->option('force') ? 'force' : 'manual')];
        } else {
            $logs = $refresh->refreshDue();
        }

        foreach ($logs as $log) {
            $this->line(sprintf('[%s] list #%d: %s', $log->status, $log->gcore_ip_list_id, $log->message));
        }

        if (trim((string) config('gcore.api_key')) !== '') {
            // Read back every other profile with list rules (read-only), so rules changed on Gcore
            // are flagged as error and fixed by applyPending right below.
            $touched = [];
            foreach ($logs as $log) {
                foreach ($log->profiles ?? [] as $p) {
                    $touched[] = (int) $p['profile_id'];
                }
            }
            foreach ($bindings->verifyAll(array_values(array_unique($touched))) as $result) {
                $this->line(sprintf('[verify:%s] profile #%d %s: %s', $result['status'], $result['profile_id'], $result['ip'] ?? '', $result['message']));
            }

            // Retry profiles that failed earlier (Gcore down, lock timeout, missing CIDRs, …).
            foreach ($bindings->applyPending() as $result) {
                $this->line(sprintf('[%s] profile #%d %s: %s', $result['status'], $result['profile_id'], $result['ip'] ?? '', $result['message']));
            }
        }

        return collect($logs)->contains(fn ($l) => $l->status === 'error') ? self::FAILURE : self::SUCCESS;
    }
}
