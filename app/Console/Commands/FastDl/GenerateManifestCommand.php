<?php

namespace Pterodactyl\Console\Commands\FastDl;

use Pterodactyl\Models\Server;
use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\FastDlManifestService;

class GenerateManifestCommand extends Command
{
    protected $signature = 'p:fastdl:manifest
                            {--servers= : Comma separated server IDs.}
                            {--nodes= : Comma separated node IDs.}
                            {--all : Every FastDL server.}
                            {--force : Also regenerate servers that already have a manifest.}';

    protected $description = 'Queue FastDL install manifests (reference installs on Wings) for existing servers.';

    public function handle(FastDlManifestService $service): int
    {
        $servers = array_filter(explode(',', (string) $this->option('servers')));
        $nodes = array_filter(explode(',', (string) $this->option('nodes')));
        if ($servers === [] && $nodes === [] && !$this->option('all')) {
            $this->error('Pass --servers, --nodes or --all.');

            return self::FAILURE;
        }

        $query = Server::query()->with(['egg', 'node'])->where('fastdl_enabled', true)->whereNull('status');
        if ($servers !== []) {
            $query->whereIn('id', $servers);
        }
        if ($nodes !== []) {
            $query->whereIn('node_id', $nodes);
        }

        $queued = 0;
        foreach ($query->get() as $server) {
            $label = "#{$server->id} {$server->name} ({$server->node->name})";
            if (!$service->supports($server)) {
                $this->line("skip {$label}: egg has no fastdl feature");
                continue;
            }

            try {
                if (!$this->option('force') && ($service->status($server)['exists'] ?? false)) {
                    $this->line("skip {$label}: already has a manifest");
                    continue;
                }
                $service->generate($server);
                $this->info("queued {$label}");
                ++$queued;
            } catch (\Throwable $exception) {
                $this->warn("fail {$label}: " . $exception->getMessage());
            }
        }

        $this->info("{$queued} server(s) queued. Each node runs one reference install at a time; follow the Wings log for progress.");

        return self::SUCCESS;
    }
}
