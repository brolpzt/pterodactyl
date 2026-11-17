<?php

namespace Pterodactyl\Console\Commands\Node;

use Pterodactyl\Models\Node;
use Illuminate\Console\Command;
use Pterodactyl\Models\XdpSyncLog;
use Pterodactyl\Services\Xdp\XdpFirewallSyncService;

class SyncXdpFirewallCommand extends Command
{
    protected $signature = 'p:xdp:sync
                            {--node= : Only this node id (dry runs also work on nodes with XDP disabled)}
                            {--dry-run : Only show what would change on the node}';

    protected $description = 'Push the XDP game firewall port map of nodes with XDP enabled to Wings.';

    public function handle(XdpFirewallSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $query = Node::query()->orderBy('id');
        if ($id = $this->option('node')) {
            $query->whereKey((int) $id);
        } else {
            $query->where('xdp_enabled', true);
        }

        $failed = false;
        foreach ($query->get() as $node) {
            if (!$dryRun && !$node->xdp_enabled) {
                $this->warn("Node {$node->name}: XDP desabilitado, ignorado.");
                continue;
            }

            try {
                $result = $service->syncNode($node, $dryRun, XdpSyncLog::TRIGGER_RECONCILE, 'reconciliação periódica');
            } catch (\Throwable $e) {
                $failed = true;
                $this->error("Node {$node->name}: {$e->getMessage()}");
                continue;
            }

            $this->line(sprintf(
                '%sNode %s: entram %d, mudam %d, saem %d, iguais %d.',
                $dryRun ? '[dry-run] ' : '',
                $node->name,
                count($result['added']),
                count($result['updated']),
                count($result['removed']),
                (int) $result['unchanged'],
            ));
            if ($this->getOutput()->isVerbose() || $dryRun) {
                foreach (['added' => '+', 'updated' => '~', 'removed' => '-'] as $key => $sign) {
                    foreach ($result[$key] as $change) {
                        $this->line(sprintf('  %s %d %s', $sign, $change['port'] ?? 0, trim(($change['from'] ?? '') . ' -> ' . ($change['to'] ?? ''), ' ->')));
                    }
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
