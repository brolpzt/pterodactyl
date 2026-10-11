<?php

namespace Pterodactyl\Services\Nftables;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Repositories\Wings\DaemonNftablesRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class NftablesFirewallSyncService
{
    public function __construct(
        private PortSlotSyncService $portSlotSync,
        private DaemonNftablesRepository $daemonNftablesRepository,
    ) {
    }

    /**
     * On server create: keep nftables_managed flags; sync chain.
     * On server delete: clear nftables_managed on this server's allocs; sync chain.
     *
     * @return list<string>
     */
    public function syncForServerLifecycle(Server $server, bool $opening): array
    {
        $server->loadMissing(['node', 'allocations']);

        $node = $server->node;
        if (!$node || !$node->nftables_enabled) {
            return [];
        }

        $allocations = $server->allocations
            ->where('node_id', $node->id)
            ->filter(fn (Allocation $a) => (bool) $a->nftables_managed);

        if ($allocations->isEmpty()) {
            return [];
        }

        if (!$opening) {
            Allocation::query()
                ->whereIn('id', $allocations->pluck('id')->all())
                ->update(['nftables_managed' => false]);
        } else {
            // Mutual exclusion with Gcore.
            Allocation::query()
                ->whereIn('id', $allocations->pluck('id')->all())
                ->update([
                    'nftables_managed' => true,
                    'gcore_protected' => false,
                ]);
        }

        try {
            return [$this->syncNode($node->fresh())];
        } catch (\Throwable $e) {
            Log::warning('Nftables sync during server lifecycle failed.', [
                'server_id' => $server->id,
                'opening' => $opening,
                'error' => $e->getMessage(),
            ]);

            return ['nftables: falha — ' . $e->getMessage()];
        }
    }

    /**
     * Push the full desired nftables rule set for this node to Wings.
     *
     * @throws DisplayException
     */
    public function syncNode(Node $node): string
    {
        if (!$node->nftables_enabled) {
            throw new DisplayException('Este node não tem nftables habilitado.');
        }

        $rules = $this->collectDesiredRules($node);

        try {
            $this->daemonNftablesRepository->setNode($node)->sync($rules);
        } catch (DaemonConnectionException $e) {
            $this->rememberLastSync($node, false, $e->getMessage(), count($rules));
            throw new DisplayException('Falha ao sincronizar nftables no Wings: ' . $e->getMessage(), $e);
        }

        $count = count($rules);
        $message = "Node {$node->name}: {$count} regra(s) enviada(s) à chain ptero_ports.";
        $this->rememberLastSync($node, true, $message, $count);

        return $message;
    }

    /**
     * @return array{ok: bool, message: string, rules: int, at: string}|null
     */
    public function lastSyncStatus(Node $node): ?array
    {
        $raw = Cache::get($this->lastSyncCacheKey($node));

        return is_array($raw) ? $raw : null;
    }

    private function rememberLastSync(Node $node, bool $ok, string $message, int $rules): void
    {
        Cache::put($this->lastSyncCacheKey($node), [
            'ok' => $ok,
            'message' => $message,
            'rules' => $rules,
            'at' => now()->toIso8601String(),
        ], now()->addDays(30));
    }

    private function lastSyncCacheKey(Node $node): string
    {
        return 'nftables:last_sync:node:' . $node->id;
    }

    /**
     * Desired open rules for the admin UI.
     *
     * @return list<array{ip: string, port: int, proto: string, server_id: int|null, server_name: string|null}>
     */
    public function listDesiredRules(Node $node): array
    {
        return $this->collectDesiredRulesDetailed($node);
    }

    /**
     * @return list<array{ip: string, port: int, proto: string}>
     */
    private function collectDesiredRules(Node $node): array
    {
        $detailed = $this->collectDesiredRulesDetailed($node);

        return array_map(static fn (array $row) => [
            'ip' => $row['ip'],
            'port' => $row['port'],
            'proto' => $row['proto'],
        ], $detailed);
    }

    /**
     * @return list<array{ip: string, port: int, proto: string, server_id: int|null, server_name: string|null}>
     */
    private function collectDesiredRulesDetailed(Node $node): array
    {
        $allocations = Allocation::query()
            ->where('node_id', $node->id)
            ->where('nftables_managed', true)
            ->whereNotNull('server_id')
            ->with(['server:id,name,egg_id,allocation_id', 'server.egg:id,port_slots,gcore_policy,gcore_proto'])
            ->orderBy('ip')
            ->orderBy('port')
            ->get();

        $out = [];
        foreach ($allocations as $allocation) {
            $port = (int) $allocation->port;
            if ($port < 1 || $port > 65535) {
                continue;
            }
            if (!$allocation->server || !$allocation->server->egg) {
                continue;
            }

            $protos = $this->portSlotSync->resolveNftablesProtosForAllocation(
                $allocation->server->egg,
                $allocation,
                (int) $allocation->server->allocation_id
            );
            if ($protos === null || $protos === []) {
                continue;
            }

            foreach ($protos as $proto) {
                $out[] = [
                    'ip' => (string) $allocation->ip,
                    'port' => $port,
                    'proto' => $proto,
                    'server_id' => (int) $allocation->server_id,
                    'server_name' => (string) $allocation->server->name,
                ];
            }
        }

        return $out;
    }
}
