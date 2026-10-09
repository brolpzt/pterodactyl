<?php

namespace Pterodactyl\Services\Gcore;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\NodeGcoreIp;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Illuminate\Support\Facades\Log;

class GcoreFirewallSyncService
{
    public function __construct(private PortSlotSyncService $portSlotSync)
    {
    }

    /**
     * On server create: mark allocations on Gcore IPs as protected and sync immediately.
     * On server delete: unmark those allocations and sync immediately.
     *
     * @return list<string>
     */
    public function syncForServerLifecycle(Server $server, bool $opening): array
    {
        $server->loadMissing(['node.gcoreIps', 'allocations']);

        $node = $server->node;
        if (!$node || !$node->gcore_enabled) {
            return [];
        }

        $gcoreIps = $node->gcoreIps->pluck('ip')->all();
        if ($gcoreIps === []) {
            return [];
        }

        $allocations = $server->allocations
            ->where('node_id', $node->id)
            ->filter(fn (Allocation $a) => in_array($a->ip, $gcoreIps, true));

        if ($allocations->isEmpty()) {
            return [];
        }

        $ips = $allocations->pluck('ip')->unique()->values()->all();

        Allocation::query()
            ->whereIn('id', $allocations->pluck('id')->all())
            ->update(['gcore_protected' => $opening]);

        $lines = [];
        foreach ($ips as $ip) {
            try {
                $lines[] = $this->syncIp($node->fresh(['gcoreIps']), (string) $ip, force: !$opening);
            } catch (\Throwable $e) {
                Log::warning('Gcore sync during server lifecycle failed.', [
                    'server_id' => $server->id,
                    'ip' => $ip,
                    'opening' => $opening,
                    'error' => $e->getMessage(),
                ]);
                $lines[] = "IP {$ip}: falha — {$e->getMessage()}";
            }
        }

        return $lines;
    }

    /**
     * Sync ACL ports on Gcore for every IP marked as Gcore-protected on the node.
     *
     * @return list<string> human-readable result lines
     *
     * @throws DisplayException
     */
    public function syncNode(Node $node): array
    {
        if (!$node->gcore_enabled) {
            throw new DisplayException('Este node não está marcado como Gcore.');
        }

        $targets = NodeGcoreIp::query()
            ->where('node_id', $node->id)
            ->pluck('ip')
            ->all();

        if ($targets === []) {
            return ['Nenhum IP marcado como Gcore neste node.'];
        }

        $lines = [];
        foreach ($targets as $ip) {
            $lines[] = $this->syncIp($node, (string) $ip);
        }

        return $lines;
    }

    /**
     * Ensure the Gcore profile for $ip has ACL rules grouped by policy,
     * without duplicating ports that already exist.
     *
     * @throws DisplayException
     */
    public function syncIp(Node $node, string $ip, bool $force = false): string
    {
        if (!$node->gcore_enabled) {
            throw new DisplayException('Este node não está marcado como Gcore.');
        }

        if ((string) config('gcore.api_key', '') === '') {
            throw new DisplayException('Configure GCORE_API_KEY no .env.');
        }

        $ipIsGcore = $node->hasGcoreIp($ip);
        if (!$ipIsGcore && !$force) {
            throw new DisplayException("O IP {$ip} não está marcado como protegido no Gcore.");
        }

        // If the IP is no longer marked Gcore, clear panel-managed ports only.
        $desired = $ipIsGcore
            ? $this->collectDesiredByPolicy($node, $ip)
            : ['ports' => [], 'protos' => []];
        $portsByPolicy = $desired['ports'];
        $protoByEgg = $desired['protos'];

        try {
            $client = GcoreClient::fromConfig();
            $profile = $this->findProfileByIp($client, $ip);
            if ($profile === null) {
                throw new DisplayException("Nenhum perfil Gcore encontrado para o IP {$ip}.");
            }

            $form = GcoreAclHelper::extractProfileFormData($profile);
            $panelAllocationPorts = $this->collectAllocationPortsOnIp($node, $ip);
            $merge = $this->mergeGroupedAcl($form['acl'], $portsByPolicy, $protoByEgg, $panelAllocationPorts);

            // No ACL delta — skip the PUT to avoid pointless Pending Update on Gcore.
            if ($merge['added'] === [] && $merge['removed'] === []) {
                return $this->formatSyncSummary(
                    $ip,
                    (int) $profile['id'],
                    $portsByPolicy,
                    $merge,
                    'sem alterações (API não atualizada)',
                    $ipIsGcore,
                );
            }

            $payload = $client->buildUpdatePayload($profile, $form['rate'], $form['geoip'], $merge['acl']);
            $updated = $client->updateProfile((int) $profile['id'], $payload);
            $status = (string) ($updated['status']['status'] ?? 'OK');

            return $this->formatSyncSummary($ip, (int) $profile['id'], $portsByPolicy, $merge, $status, $ipIsGcore);
        } catch (DisplayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DisplayException('Gcore sync falhou para ' . $ip . ': ' . $e->getMessage(), $e);
        }
    }

    /**
     * @return array{
     *   ports: array<string, list<int>>,
     *   protos: array<string, list<string>>,
     * }
     */
    private function collectDesiredByPolicy(Node $node, string $ip): array
    {
        $allocations = Allocation::query()
            ->where('node_id', $node->id)
            ->where('ip', $ip)
            ->where('gcore_protected', true)
            ->whereNotNull('server_id')
            ->with(['server:id,egg_id,allocation_id', 'server.egg:id,port_slots,gcore_policy,gcore_proto'])
            ->orderBy('port')
            ->get();

        /** @var array<string, array<int, true>> $map */
        $map = [];
        /** @var array<string, list<string>> $protos */
        $protos = [];
        foreach ($allocations as $allocation) {
            $port = (int) $allocation->port;
            if ($port < 1 || $port > 65535) {
                continue;
            }

            // Free allocations must never open ACL rules (would fall back to allowlist).
            if (!$allocation->server_id || !$allocation->server || !$allocation->server->egg) {
                continue;
            }

            $resolved = $this->portSlotSync->resolveGcoreForAllocation(
                $allocation->server->egg,
                $allocation,
                (int) $allocation->server->allocation_id
            );
            if ($resolved === null) {
                // No ACL configured for this port slot — skip.
                continue;
            }

            $policy = $resolved['policy'];
            $map[$policy][$port] = true;

            if (!isset($protos[$policy]) && !empty($resolved['proto'])) {
                $protos[$policy] = [$resolved['proto']];
            }
        }

        $out = [];
        foreach ($map as $policy => $ports) {
            $list = array_map('intval', array_keys($ports));
            sort($list);
            $out[$policy] = $list;
        }

        ksort($out);

        return ['ports' => $out, 'protos' => $protos];
    }

    /**
     * Every allocation port on this IP (panel-owned). Used so sync only adds/removes
     * those ports and never strips unrelated manual ports on the same policy rule.
     *
     * @return list<int>
     */
    private function collectAllocationPortsOnIp(Node $node, string $ip): array
    {
        $ports = Allocation::query()
            ->where('node_id', $node->id)
            ->where('ip', $ip)
            ->pluck('port')
            ->map(fn ($port) => (int) $port)
            ->filter(fn (int $port) => $port >= 1 && $port <= 65535)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return array_values($ports);
    }

    /**
     * Rebuild ACL without overwriting manual (non-panel) rules, preserving order.
     *
     * - Manual / infra rules stay intact and keep relative order.
     * - Panel-managed game policies are rebuilt (ports only) and placed after the
     *   first N leading rules (config gcore.acl_game_rules_after, default 5).
     * - Rules with sip/sport filters are always preserved in place.
     *
     * @param list<array<string, mixed>> $acl
     * @param array<string, list<int>> $desiredByPolicy ports the panel wants open
     * @param array<string, list<string>> $protoByEgg proto from egg when creating a new rule
     * @param list<int> $panelAllocationPorts all allocation ports on this IP
     * @return array{
     *   acl: list<array<string, mixed>>,
     *   added: array<string, list<int>>,
     *   removed: array<string, list<int>>,
     *   skipped_existing: array<string, list<int>>,
     * }
     */
    private function mergeGroupedAcl(
        array $acl,
        array $desiredByPolicy,
        array $protoByEgg = [],
        array $panelAllocationPorts = [],
    ): array {
        /** @var list<array<string, mixed>> $manualRules original order, excluding panel-managed policies */
        $manualRules = [];
        /** @var array<string, array<int, true>> $existingInPreserved */
        $existingInPreserved = [];
        /** @var array<string, array<int, true>> $existingInGroupable */
        $existingInGroupable = [];
        /** @var array<string, list<string>> $protoByPolicy */
        $protoByPolicy = [];

        $panelPortSet = [];
        foreach ($panelAllocationPorts as $port) {
            $panelPortSet[(int) $port] = true;
        }

        foreach ($acl as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if ($this->isGroupableRule($rule)) {
                $policy = trim((string) ($rule['policy'] ?? ''));
                if ($policy === '' || !in_array($policy, GcoreClient::POLICIES, true)) {
                    $manualRules[] = $this->normalizeRuleShape($rule, $policy !== '' ? $policy : null);
                    continue;
                }

                // Panel-managed game policy: absorb ports, drop from manual order (re-inserted later).
                if (array_key_exists($policy, $desiredByPolicy)) {
                    if (!isset($protoByPolicy[$policy])) {
                        $protoByPolicy[$policy] = $this->ruleProtoList($rule);
                    }
                    foreach ($this->rulePorts($rule) as $port) {
                        $existingInGroupable[$policy][$port] = true;
                    }
                    continue;
                }

                $manualRules[] = $this->normalizeRuleShape($rule, $policy);
                continue;
            }

            $policy = trim((string) ($rule['policy'] ?? ''));
            if ($policy === '') {
                $policy = $this->normalizePolicy(null);
            }
            foreach ($this->rulePorts($rule) as $port) {
                $existingInPreserved[$policy][$port] = true;
            }
            $manualRules[] = $this->normalizeRuleShape($rule, $policy);
        }

        $added = [];
        $removed = [];
        $skipped = [];
        $grouped = [];

        $policies = array_values(array_unique(array_merge(
            array_keys($desiredByPolicy),
            array_keys($existingInGroupable),
        )));
        sort($policies);

        foreach ($policies as $policy) {
            $desired = array_values(array_unique(array_map('intval', $desiredByPolicy[$policy] ?? [])));
            sort($desired);

            $previous = array_map('intval', array_keys($existingInGroupable[$policy] ?? []));
            sort($previous);

            // Keep ports that are not panel allocations (manual extras on the same policy).
            $manualPorts = [];
            foreach ($previous as $port) {
                if (!isset($panelPortSet[$port])) {
                    $manualPorts[$port] = true;
                }
            }

            $portsForRule = $manualPorts;
            foreach ($desired as $port) {
                if (isset($existingInPreserved[$policy][$port])) {
                    $skipped[$policy][] = $port;
                    continue;
                }
                $portsForRule[$port] = true;
            }

            $finalPorts = array_map('intval', array_keys($portsForRule));
            sort($finalPorts);

            $added[$policy] = array_values(array_diff($finalPorts, $previous));
            $removed[$policy] = array_values(array_filter(
                array_diff($previous, $finalPorts),
                fn (int $port) => isset($panelPortSet[$port]),
            ));

            if ($finalPorts === []) {
                continue;
            }

            $proto = $protoByPolicy[$policy] ?? $protoByEgg[$policy] ?? ['any'];
            if ($proto === []) {
                $proto = ['any'];
            }

            $grouped[] = [
                'policy' => $policy,
                'sip_list' => [],
                'dport_list' => $finalPorts,
                'proto_list' => $proto,
                'sport_list' => [],
            ];
        }

        $added = array_filter($added, fn ($ports) => $ports !== []);
        $removed = array_filter($removed, fn ($ports) => $ports !== []);
        $skipped = array_filter($skipped, fn ($ports) => $ports !== []);

        // Keep first N manual/infra rules on top; panel game rules go below that block.
        $after = max(0, (int) config('gcore.acl_game_rules_after', 5));
        $head = array_slice($manualRules, 0, $after);
        $tail = array_slice($manualRules, $after);

        return [
            'acl' => array_values(array_merge($head, $grouped, $tail)),
            'added' => $added,
            'removed' => $removed,
            'skipped_existing' => $skipped,
        ];
    }

    /**
     * @param array<string, list<int>> $portsByPolicy
     * @param array{
     *   acl: list<array<string, mixed>>,
     *   added: array<string, list<int>>,
     *   removed: array<string, list<int>>,
     *   skipped_existing: array<string, list<int>>,
     * } $merge
     */
    private function formatSyncSummary(
        string $ip,
        int $profileId,
        array $portsByPolicy,
        array $merge,
        string $status,
        bool $ipIsGcore,
    ): string {
        $lines = [
            sprintf('IP %s · perfil %d · %s', $ip, $profileId, $status),
        ];

        if (!$ipIsGcore) {
            $lines[] = 'IP removido do Gcore — regras gerenciadas limpas na ACL.';

            return implode("\n", $lines);
        }

        $added = $merge['added'];
        $removed = $merge['removed'];
        $skipped = $merge['skipped_existing'];

        if ($portsByPolicy === [] && $added === [] && $removed === []) {
            $lines[] = 'Nenhuma porta com servidor + política ACL para sincronizar.';

            return implode("\n", $lines);
        }

        if ($portsByPolicy !== []) {
            $lines[] = 'Portas no painel:';
            foreach ($portsByPolicy as $policy => $ports) {
                $count = count($ports);
                $allSkipped = isset($skipped[$policy])
                    && count($skipped[$policy]) === $count
                    && array_values(array_diff($ports, $skipped[$policy])) === [];
                $note = $allSkipped ? ' (já na ACL)' : '';
                $lines[] = sprintf(
                    '  • %s: %d porta%s%s',
                    $policy,
                    $count,
                    $count === 1 ? '' : 's',
                    $note
                );
            }
        }

        $hasDelta = $added !== [] || $removed !== [];
        if (!$hasDelta) {
            $lines[] = 'Resultado: sem alterações (tudo já estava na ACL).';

            return implode("\n", $lines);
        }

        $lines[] = 'Alterações enviadas:';
        foreach ($added as $policy => $ports) {
            $lines[] = sprintf('  • + %s: %s', $policy, implode(', ', $ports));
        }
        foreach ($removed as $policy => $ports) {
            $lines[] = sprintf('  • − %s: %s', $policy, implode(', ', $ports));
        }

        if ($skipped !== []) {
            $skipCount = array_sum(array_map('count', $skipped));
            $lines[] = sprintf(
                '  • %d porta%s ignorada%s (já existiam na ACL).',
                $skipCount,
                $skipCount === 1 ? '' : 's',
                $skipCount === 1 ? '' : 's'
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findProfileByIp(GcoreClient $client, string $ip): ?array
    {
        foreach ($client->listProfiles() as $profile) {
            if (!is_array($profile)) {
                continue;
            }
            if ((string) ($profile['ip_address'] ?? '') === $ip) {
                return $profile;
            }
        }

        return null;
    }

    private function normalizePolicy(?string $policy): string
    {
        $policy = trim((string) ($policy ?: config('gcore.default_policy', 'allowlist')));
        if (!in_array($policy, GcoreClient::POLICIES, true)) {
            return 'allowlist';
        }

        return $policy;
    }

    /**
     * Mergeable rules: same ACL policy, no sip/sport filters.
     * Proto may be any/udp/tcp/… — we still collapse into one rule per policy.
     *
     * @param array<string, mixed> $rule
     */
    private function isGroupableRule(array $rule): bool
    {
        $policy = trim((string) ($rule['policy'] ?? ''));
        if ($policy === '' || !in_array($policy, GcoreClient::POLICIES, true)) {
            return false;
        }

        // Catch-all / default template rules must stay preserved (order matters).
        if (str_starts_with(strtolower($policy), 'default')) {
            return false;
        }

        $sip = $this->ruleStringList($rule['sip_list'] ?? []);
        $sport = $this->rulePorts(['dport_list' => $rule['sport_list'] ?? []]);

        return $sip === [] && $sport === [];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function ruleStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return array_values($out);
    }

    /**
     * @param array<string, mixed> $rule
     * @return list<string>
     */
    private function ruleProtoList(array $rule): array
    {
        $raw = $rule['proto_list'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $proto) {
            $proto = strtolower(trim((string) $proto));
            if ($proto !== '') {
                $out[] = $proto;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $rule
     * @return list<int>
     */
    private function rulePorts(array $rule): array
    {
        $raw = $rule['dport_list'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $ports = [];
        foreach ($raw as $port) {
            if (is_int($port) || (is_string($port) && ctype_digit($port))) {
                $p = (int) $port;
                if ($p >= 1 && $p <= 65535) {
                    $ports[$p] = true;
                }
            }
        }

        $list = array_map('intval', array_keys($ports));
        sort($list);

        return $list;
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function normalizeRuleShape(array $rule, ?string $policy = null): array
    {
        $policy = trim((string) ($policy ?? $rule['policy'] ?? ''));
        if ($policy === '') {
            $policy = $this->normalizePolicy(null);
        }

        return [
            'policy' => $policy,
            'sip_list' => array_values(is_array($rule['sip_list'] ?? null) ? $rule['sip_list'] : []),
            'dport_list' => $this->rulePorts($rule),
            'proto_list' => array_values(is_array($rule['proto_list'] ?? null) ? $rule['proto_list'] : []),
            'sport_list' => array_values(array_filter(
                is_array($rule['sport_list'] ?? null) ? $rule['sport_list'] : [],
                static fn ($p) => is_int($p) || (is_string($p) && ctype_digit((string) $p))
            )),
        ];
    }
}
