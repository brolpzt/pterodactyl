<?php

namespace Pterodactyl\Services\Xdp;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Support\ServerType;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Repositories\Wings\DaemonXdpRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Keeps the node's XDP game firewall (game_firewall on the node, through Wings) equal
 * to the ports of the servers on that node. The panel owns the whole map: ports it
 * does not send are removed from the node.
 */
class XdpFirewallSyncService
{
    public function __construct(
        private PortSlotSyncService $portSlotSync,
        private DaemonXdpRepository $repository,
    ) {
    }

    /**
     * Desired map plus the details shown in the admin page.
     *
     * @return array{
     *     ports: array<int, string>,
     *     rows: list<array{port: int, ip: string, profile: string, server_id: int, server_name: string, slot: string|null}>,
     *     conflicts: list<array{port: int, profile: string, kept: string, server_id: int, server_name: string}>
     * }
     */
    public function desiredPortsDetailed(Node $node): array
    {
        $allocations = Allocation::query()
            ->where('node_id', $node->id)
            ->whereNotNull('server_id')
            ->with(['server:id,name,egg_id,allocation_id', 'server.egg'])
            ->orderBy('port')
            ->orderBy('id')
            ->get();

        $ports = [];
        $rows = [];
        $conflicts = [];
        foreach ($allocations as $allocation) {
            $server = $allocation->server;
            $port = (int) $allocation->port;
            if (!$server || !$server->egg || $port < 1 || $port > 65535) {
                continue;
            }

            $primaryId = (int) $server->allocation_id;
            $profile = $this->portSlotSync->resolveXdpProfileForAllocation($server->egg, $allocation, $primaryId, ServerType::isTs3($server));
            if ($profile === null) {
                continue;
            }

            // The XDP map is keyed by port only: the first server on a port decides its profile.
            if (isset($ports[$port])) {
                if ($ports[$port] !== $profile) {
                    $conflicts[] = [
                        'port' => $port,
                        'profile' => $profile,
                        'kept' => $ports[$port],
                        'server_id' => (int) $server->id,
                        'server_name' => (string) $server->name,
                    ];
                }
                continue;
            }

            $ports[$port] = $profile;
            $slot = $this->portSlotSync->describeSlotForAllocation($server->egg, $allocation, $primaryId);
            $rows[] = [
                'port' => $port,
                'ip' => (string) $allocation->ip,
                'profile' => $profile,
                'server_id' => (int) $server->id,
                'server_name' => (string) $server->name,
                'slot' => $slot['name'] ?? null,
            ];
        }

        ksort($ports);

        return ['ports' => $ports, 'rows' => $rows, 'conflicts' => $conflicts];
    }

    /**
     * @return array<int, string>
     */
    public function desiredPorts(Node $node): array
    {
        return $this->desiredPortsDetailed($node)['ports'];
    }

    /**
     * Sends the desired map to Wings. On a dry run Wings only reports the difference.
     *
     * @return array{dry_run: bool, added: list<array>, updated: list<array>, removed: list<array>, unchanged: int}
     *
     * @throws DisplayException
     */
    public function syncNode(Node $node, bool $dryRun = false): array
    {
        if (!$dryRun && !$node->xdp_enabled) {
            throw new DisplayException('Este node não tem o firewall XDP habilitado.');
        }

        $ports = $this->desiredPorts($node);

        try {
            $result = $this->repository->setNode($node)->sync($ports, $dryRun);
        } catch (DaemonConnectionException $e) {
            if (!$dryRun) {
                $this->rememberLastSync($node, false, $e->getMessage(), count($ports));
            }
            throw new DisplayException('Falha ao falar com o Wings sobre o firewall XDP: ' . $e->getMessage(), $e);
        }

        $result += ['dry_run' => $dryRun, 'added' => [], 'updated' => [], 'removed' => [], 'unchanged' => 0];
        foreach (['added', 'updated', 'removed'] as $key) {
            $result[$key] = is_array($result[$key]) ? $result[$key] : [];
        }

        if (!$dryRun) {
            $this->rememberLastSync($node, true, sprintf(
                'Node %s: %d porta(s) protegida(s); entraram %d, mudaram %d, saíram %d.',
                $node->name,
                count($ports),
                count($result['added']),
                count($result['updated']),
                count($result['removed']),
            ), count($ports));
        }

        return $result;
    }

    /**
     * @return array{ok: bool, message: string, ports: int, at: string}|null
     */
    public function lastSyncStatus(Node $node): ?array
    {
        $raw = Cache::get($this->lastSyncCacheKey($node));

        return is_array($raw) ? $raw : null;
    }

    private function rememberLastSync(Node $node, bool $ok, string $message, int $ports): void
    {
        Cache::put($this->lastSyncCacheKey($node), [
            'ok' => $ok,
            'message' => $message,
            'ports' => $ports,
            'at' => now()->toIso8601String(),
        ], now()->addDays(30));
    }

    private function lastSyncCacheKey(Node $node): string
    {
        return 'xdp:last_sync:node:' . $node->id;
    }
}
