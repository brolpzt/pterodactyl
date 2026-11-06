<?php

namespace Pterodactyl\Console\Commands\FastDl;

use Illuminate\Console\Command;
use Pterodactyl\Models\FastDlNode;
use Pterodactyl\Services\FastDl\FastDlBucketAuditService;

class AuditBucketCommand extends Command
{
    protected $signature = 'p:fastdl:audit
                            {node : FastDL node ID.}
                            {--clean : Delete the folders of deleted servers, servers with FastDL off and servers in another location.}';

    protected $description = 'Map the FastDL bucket folders to servers and optionally remove the unused ones.';

    public function handle(FastDlBucketAuditService $service): int
    {
        $node = FastDlNode::query()->findOrFail($this->argument('node'));
        $audit = $service->audit($node);

        $this->table(['Folder', 'Status', 'Server', 'Objects', 'MiB', 'Last change'], array_map(fn ($row) => [
            $row['folder'] === '' ? '(root files)' : $row['folder'],
            $row['status'],
            $row['server'] ? "#{$row['server']->id} {$row['server']->name}" : '-',
            $row['objects'],
            round($row['bytes'] / 1048576, 1),
            $row['last_modified'] ?? '-',
        ], $audit['folders']));

        $removable = array_values(array_filter($audit['folders'], fn ($row) => in_array($row['status'], FastDlBucketAuditService::REMOVABLE, true)));
        $this->info(sprintf('%d folder(s) can be removed (%.1f MiB).', count($removable), array_sum(array_column($removable, 'bytes')) / 1048576));

        if ($this->option('clean') && $removable !== []) {
            if (!$this->confirm('Delete these ' . count($removable) . ' folder(s) from the bucket?')) {
                return self::SUCCESS;
            }
            foreach ($service->clean($node, array_column($removable, 'folder')) as $folder => $objects) {
                $this->line("deleted {$folder} ({$objects} objects)");
            }
        }

        return self::SUCCESS;
    }
}
