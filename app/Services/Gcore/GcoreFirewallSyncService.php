<?php

namespace Pterodactyl\Services\Gcore;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\NodeGcoreIp;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListBindingService;

class GcoreFirewallSyncService
{
    private GcoreLinkedRuleService $linkedRules;

    public function __construct(private PortSlotSyncService $portSlotSync, ?GcoreLinkedRuleService $linkedRules = null)
    {
        $this->linkedRules = $linkedRules ?? new GcoreLinkedRuleService();
    }

    /**
     * On server create: mark allocations on Gcore IPs as protected and sync immediately.
     * On server delete: unmark those allocations and sync. The sync itself is queued.
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

        $payload = ['gcore_protected' => $opening];
        if ($opening) {
            // Mutual exclusion with local nftables management.
            $payload['nftables_managed'] = false;
        }

        Allocation::query()
            ->whereIn('id', $allocations->pluck('id')->all())
            ->update($payload);

        // The Gcore call itself runs on the queue: creating/deleting a server never waits for it.
        // The allocation flags above are already saved, so the job sees the new state.
        $reason = ($opening ? 'servidor criado: ' : 'servidor excluído: ') . $server->name . ' (#' . $server->id . ')';
        $lines = [];
        foreach ($ips as $ip) {
            $run = app(GcoreSyncQueue::class)->ports($node, (string) $ip, $reason, force: !$opening);
            $lines[] = "IP {$ip}: sincronização Gcore na fila (#{$run->id})";
        }

        return $lines;
    }

    /**
     * Queue a port sync for every IP marked as Gcore-protected on the node.
     *
     * @return list<\Pterodactyl\Models\GcoreSyncRun>
     *
     * @throws DisplayException
     */
    public function queueNode(Node $node, string $reason): array
    {
        if (!$node->gcore_enabled) {
            throw new DisplayException('Este node não está marcado como Gcore.');
        }

        $runs = [];
        foreach (NodeGcoreIp::query()->where('node_id', $node->id)->pluck('ip') as $ip) {
            $runs[] = app(GcoreSyncQueue::class)->ports($node, (string) $ip, $reason);
        }

        return $runs;
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
    public function syncIp(Node $node, string $ip, bool $force = false, bool $dryRun = false): string
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
            : ['ports' => [], 'by_proto' => [], 'links' => []];
        $portsByPolicy = $desired['ports'];
        $portsByPolicyProto = $desired['by_proto'];
        $linkPorts = $desired['links'];
        $linkedDefs = $this->linkedRules->inUse();

        try {
            $client = GcoreClient::fromConfig();
            $profile = $this->findProfileByIp($client, $ip);
            if ($profile === null) {
                throw new DisplayException("Nenhum perfil Gcore encontrado para o IP {$ip}.");
            }

            $panelAllocationPorts = $this->collectAllocationPortsOnIp($node, $ip);

            // Same lock as the editor and the IP-list refresh: re-read inside it so a concurrent
            // write to this profile is not overwritten.
            return GcoreIpListBindingService::withProfileLock((int) $profile['id'], function () use ($client, $profile, $ip, $ipIsGcore, $portsByPolicy, $portsByPolicyProto, $panelAllocationPorts, $linkPorts, $linkedDefs, $dryRun) {
                $profile = $client->getProfile((int) $profile['id']);
                $form = GcoreAclHelper::extractProfileFormData($profile);
                $merge = $this->planAcl($form['acl'], $portsByPolicyProto, $panelAllocationPorts, $linkedDefs, $linkPorts);

                // No ACL delta — skip the PUT to avoid pointless Pending Update on Gcore.
                if ($merge['added'] === [] && $merge['removed'] === [] && $merge['linked'] === []) {
                    return $this->formatSyncSummary(
                        $ip,
                        (int) $profile['id'],
                        $portsByPolicy,
                        $merge,
                        'sem alterações (API não atualizada)',
                        $ipIsGcore,
                    );
                }

                if ($dryRun) {
                    return $this->formatSyncSummary($ip, (int) $profile['id'], $portsByPolicy, $merge, 'PRÉVIA (nada enviado)', $ipIsGcore)
                        . "\nACL resultante:\n" . $this->describeAcl($merge['acl']);
                }

                $payload = $client->buildUpdatePayload($profile, $form['rate'], $form['geoip'], $merge['acl']);
                $put = $client->updateProfileChecked((int) $profile['id'], $profile, $payload);
                $status = (string) ($put['response']['status']['status'] ?? 'OK') . ($put['recovered'] === null ? '' : ' (' . $put['recovered'] . ')');

                return $this->formatSyncSummary($ip, (int) $profile['id'], $portsByPolicy, $merge, $status, $ipIsGcore);
            });
        } catch (DisplayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DisplayException('Gcore sync falhou para ' . $ip . ': ' . $e->getMessage(), $e);
        }
    }

    /**
     * @return array{
     *   ports: array<string, list<int>>,
     *   by_proto: array<string, array<string, list<int>>>,
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

        /** @var array<string, array<string, array<int, true>>> $byProto */
        $byProto = [];
        /** @var array<int, array<int, true>> $links linked rule id => ports */
        $links = [];
        foreach ($allocations as $allocation) {
            $port = (int) $allocation->port;
            if ($port < 1 || $port > 65535) {
                continue;
            }

            // Free allocations must never open ACL rules (would fall back to allowlist).
            if (!$allocation->server_id || !$allocation->server || !$allocation->server->egg) {
                continue;
            }

            foreach ($this->portSlotSync->resolveGcoreLinksForAllocation($allocation->server->egg, $allocation, (int) $allocation->server->allocation_id) as $linkId) {
                $links[(int) $linkId][$port] = true;
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
            $proto = strtolower(trim((string) ($resolved['proto'] ?? 'any')));
            if ($proto === '') {
                $proto = 'any';
            }
            $byProto[$policy][$proto][$port] = true;
        }

        /** @var array<string, array<string, list<int>>> $byProtoOut */
        $byProtoOut = [];
        /** @var array<string, list<int>> $flat */
        $flat = [];
        foreach ($byProto as $policy => $protos) {
            $flatPorts = [];
            foreach ($protos as $proto => $ports) {
                $list = array_map('intval', array_keys($ports));
                sort($list);
                $byProtoOut[$policy][$proto] = $list;
                foreach ($list as $port) {
                    $flatPorts[$port] = true;
                }
            }
            ksort($byProtoOut[$policy]);
            $flatList = array_map('intval', array_keys($flatPorts));
            sort($flatList);
            $flat[$policy] = $flatList;
        }

        ksort($byProtoOut);
        ksort($flat);

        $linksOut = [];
        foreach ($links as $id => $ports) {
            $list = array_map('intval', array_keys($ports));
            sort($list);
            $linksOut[$id] = $list;
        }

        return ['ports' => $flat, 'by_proto' => $byProtoOut, 'links' => $linksOut];
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
     * Patch ACL in place: never reorder existing rules.
     *
     * - Frozen infra (DROP, geo, dns-trust, any rule with sip/sport, default-*, …) stays at the same index.
     * - Panel only adds/removes dports on editable game rules (same policy+proto),
     *   including ratelimiter-* and tcp-server. Manual (non-allocation) ports stay.
     * - A port already present on an earlier rule of the same policy+proto is
     *   dropped from later duplicates (first match wins, so behaviour is unchanged).
     * - Missing policy+proto pairs are inserted before the trailing DROP block at the
     *   end (else before trailing default-*, else at the end).
     * - If a matching game rule already exists (same policy+proto), only dports change.
     * - Never invents DROP/allowlist/other infra rules.
     *
     * @param list<array<string, mixed>> $acl
     * @param array<string, array<string, list<int>>> $desiredByPolicyProto policy => proto => ports
     * @param list<int> $panelAllocationPorts all allocation ports on this IP
     *
     * @return array{
     *   acl: list<array<string, mixed>>,
     *   added: array<string, list<int>>,
     *   removed: array<string, list<int>>,
     *   skipped_existing: array<string, list<int>>,
     * }
     */
    /**
     * Full sync plan: game rules (mergeGroupedAcl) then linked rules. Linked rules are marked
     * first so the game merge leaves them alone (they carry sip/sport or DROP and would
     * otherwise count as frozen rules covering those ports).
     *
     * @param list<array<string, mixed>> $acl
     * @param array<string, array<string, list<int>>> $desiredByPolicyProto
     * @param list<int> $panelAllocationPorts
     * @param \Illuminate\Support\Collection<int, \Pterodactyl\Models\GcoreLinkedRule> $linkedDefs
     * @param array<int, list<int>> $linkPorts
     *
     * @return array{acl: list<array<string, mixed>>, added: array, removed: array, skipped_existing: array, linked: list<string>}
     */
    public function planAcl(array $acl, array $desiredByPolicyProto, array $panelAllocationPorts, $linkedDefs, array $linkPorts): array
    {
        $marked = $this->linkedRules->mark($acl, $linkedDefs);
        $merge = $this->mergeGroupedAcl($marked, $desiredByPolicyProto, $panelAllocationPorts);
        $linked = $this->linkedRules->apply($merge['acl'], $linkedDefs, $linkPorts, $panelAllocationPorts);
        $merge['acl'] = $linked['acl'];
        $merge['linked'] = $linked['changes'];

        return $merge;
    }

    private function mergeGroupedAcl(
        array $acl,
        array $desiredByPolicyProto,
        array $panelAllocationPorts = [],
    ): array {
        $panelPortSet = [];
        foreach ($panelAllocationPorts as $port) {
            $panelPortSet[(int) $port] = true;
        }

        /** @var array<string, array<string, array<int, true>>> $remainingDesired */
        $remainingDesired = [];
        foreach ($desiredByPolicyProto as $policy => $byProto) {
            foreach ($byProto as $protoKey => $ports) {
                foreach ($ports as $port) {
                    $remainingDesired[$policy][$protoKey][(int) $port] = true;
                }
            }
        }

        /** @var array<string, array<int, true>> $portsInFrozen */
        $portsInFrozen = [];
        /** @var array<string, array<string, true>> $claimedProto */
        $claimedProto = [];
        /** @var array<string, array<string, array<int, true>>> $placed policy => proto => port */
        $placed = [];
        /** @var int|null $firstDroppedAt index in $out where the first emptied game rule stood */
        $firstDroppedAt = null;
        /** @var list<array<string, mixed>> $out */
        $out = [];
        $added = [];
        $removed = [];
        $skipped = [];

        foreach ($acl as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (isset($rule[GcoreLinkedRuleService::MARK])) {
                // Linked rule: ports are set afterwards by GcoreLinkedRuleService::apply().
                $out[] = $rule;
                continue;
            }

            $normalized = $this->normalizeRuleShape($rule);
            $policy = (string) $normalized['policy'];
            $protoList = $this->ruleProtoList($normalized);
            if ($protoList === []) {
                $protoList = ['any'];
            }
            $protoKey = $this->protoKey($protoList);
            $currentPorts = $this->rulePorts($normalized);

            if (!$this->isPanelEditableGameRule($normalized)) {
                foreach ($currentPorts as $port) {
                    $portsInFrozen[$policy][$port] = true;
                    // Desired port already covered by a frozen rule of the same policy → skip.
                    if (!isset($remainingDesired[$policy])) {
                        continue;
                    }
                    foreach ($remainingDesired[$policy] as $pk => $wantPorts) {
                        if (!isset($wantPorts[$port])) {
                            continue;
                        }
                        $skipped[$policy][] = $port;
                        unset($remainingDesired[$policy][$pk][$port]);
                        if ($remainingDesired[$policy][$pk] === []) {
                            unset($remainingDesired[$policy][$pk]);
                        }
                    }
                    if (($remainingDesired[$policy] ?? []) === []) {
                        unset($remainingDesired[$policy]);
                    }
                }
                $out[] = $normalized;
                continue;
            }

            // Keep non-panel (manual) ports on this game rule.
            $keep = [];
            foreach ($currentPorts as $port) {
                if (!isset($panelPortSet[$port]) && !isset($placed[$policy][$protoKey][$port])) {
                    $keep[$port] = true;
                }
            }

            $assignDesired = false;
            if (!isset($claimedProto[$policy][$protoKey])) {
                $assignDesired = true;
                $claimedProto[$policy][$protoKey] = true;
            }

            $desiredHere = [];
            if ($assignDesired && isset($remainingDesired[$policy][$protoKey])) {
                foreach (array_keys($remainingDesired[$policy][$protoKey]) as $port) {
                    if (isset($portsInFrozen[$policy][$port])) {
                        $skipped[$policy][] = $port;
                        continue;
                    }
                    $desiredHere[$port] = true;
                }
                unset($remainingDesired[$policy][$protoKey]);
                if (($remainingDesired[$policy] ?? []) === []) {
                    unset($remainingDesired[$policy]);
                }
            }

            $finalPorts = array_map('intval', array_keys($keep + $desiredHere));
            sort($finalPorts);
            foreach ($finalPorts as $port) {
                $placed[$policy][$protoKey][$port] = true;
            }

            // Panel ports no longer wanted here, plus duplicates of an earlier same policy+proto rule.
            $addedPorts = array_values(array_diff($finalPorts, $currentPorts));
            $removedPorts = array_values(array_diff($currentPorts, $finalPorts));

            if ($addedPorts !== []) {
                $added[$policy] = array_values(array_unique(array_merge($added[$policy] ?? [], $addedPorts)));
                sort($added[$policy]);
            }
            if ($removedPorts !== []) {
                $removed[$policy] = array_values(array_unique(array_merge($removed[$policy] ?? [], $removedPorts)));
                sort($removed[$policy]);
            }

            // Empty game rule after stripping panel ports / duplicates → drop only this line.
            if ($finalPorts === []) {
                $firstDroppedAt ??= count($out);
                continue;
            }

            $normalized['dport_list'] = $finalPorts;
            $normalized['proto_list'] = $protoKey === 'any' ? ['any'] : explode(',', $protoKey);
            $out[] = $normalized;
        }

        // New game rules: before trailing DROP (last DROP block at end), else before
        // trailing default-*, else at end. Never reorder existing rows.
        // A rule that replaces an emptied one (e.g. egg changed policy) takes its slot.
        $insertAt = $firstDroppedAt ?? $this->findNewGameRuleInsertIndex($out);

        $toInsert = [];
        foreach ($remainingDesired as $policy => $byProto) {
            foreach ($byProto as $protoKey => $portsMap) {
                $ports = array_map('intval', array_keys($portsMap));
                sort($ports);
                if ($ports === []) {
                    continue;
                }
                $toInsert[] = [
                    'policy' => $policy,
                    'sip_list' => [],
                    'dport_list' => $ports,
                    'proto_list' => $protoKey === 'any' ? ['any'] : explode(',', $protoKey),
                    'sport_list' => [],
                ];
                $added[$policy] = array_values(array_unique(array_merge($added[$policy] ?? [], $ports)));
                sort($added[$policy]);
            }
        }

        if ($toInsert !== []) {
            if ($insertAt === null) {
                $out = array_merge($out, $toInsert);
            } else {
                array_splice($out, $insertAt, 0, $toInsert);
            }
        }

        $added = array_filter($added, fn ($ports) => $ports !== []);
        $removed = array_filter($removed, fn ($ports) => $ports !== []);
        $skipped = array_filter($skipped, fn ($ports) => $ports !== []);

        return [
            'acl' => array_values($out),
            'added' => $added,
            'removed' => $removed,
            'skipped_existing' => $skipped,
        ];
    }

    /**
     * Index where brand-new game rules should be spliced in without moving others.
     *
     * Prefers immediately before the trailing DROP block at the end of the table
     * (skipping a trailing default-* suffix). Falls back to before trailing
     * default-*, then null (= append).
     *
     * @param list<array<string, mixed>> $acl
     */
    private function findNewGameRuleInsertIndex(array $acl): ?int
    {
        $n = count($acl);
        if ($n === 0) {
            return null;
        }

        $i = $n - 1;

        // Skip trailing default-* catch-alls.
        while ($i >= 0) {
            $policy = strtolower(trim((string) ($acl[$i]['policy'] ?? '')));
            if (!str_starts_with($policy, 'default')) {
                break;
            }
            --$i;
        }

        if ($i < 0) {
            return 0;
        }

        // Trailing DROP block at the end (after games / before defaults).
        if (strcasecmp(trim((string) ($acl[$i]['policy'] ?? '')), 'DROP') === 0) {
            $dropStart = $i;
            while ($dropStart > 0
                && strcasecmp(trim((string) ($acl[$dropStart - 1]['policy'] ?? '')), 'DROP') === 0
            ) {
                --$dropStart;
            }

            return $dropStart;
        }

        // No trailing DROP: insert before the first trailing default (if we skipped any).
        if ($i < $n - 1) {
            return $i + 1;
        }

        return null;
    }

    /**
     * Rules the panel may edit (dport add/remove only). Infra stays frozen in place.
     *
     * @param array<string, mixed> $rule
     */
    private function isPanelEditableGameRule(array $rule): bool
    {
        if (!$this->isGroupableRule($rule)) {
            return false;
        }

        $policy = trim((string) ($rule['policy'] ?? ''));

        return !$this->isFrozenAclPolicy($policy);
    }

    private function isFrozenAclPolicy(string $policy): bool
    {
        $policy = trim($policy);
        if ($policy === '' || str_starts_with(strtolower($policy), 'default')) {
            return true;
        }

        // allowlist is editable like a game rule (egg slots use it); allowlist rows with
        // sip/sport are manual grants and stay frozen via isGroupableRule().
        return in_array($policy, [
            'DROP',
            'geo',
            'dns-trust',
        ], true);
    }

    /**
     * @param list<string> $protoList
     */
    private function protoKey(array $protoList): string
    {
        $normalized = [];
        foreach ($protoList as $proto) {
            $proto = strtolower(trim((string) $proto));
            if ($proto !== '') {
                $normalized[] = $proto;
            }
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized === [] ? 'any' : implode(',', $normalized);
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

        if ($portsByPolicy === [] && $added === [] && $removed === [] && ($merge['linked'] ?? []) === []) {
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

        $linked = $merge['linked'] ?? [];
        $hasDelta = $added !== [] || $removed !== [] || $linked !== [];
        if (!$hasDelta) {
            $lines[] = 'Resultado: sem alterações (tudo já estava na ACL).';

            return implode("\n", $lines);
        }

        $lines[] = 'Alterações enviadas:';
        foreach ($linked as $change) {
            $lines[] = '  • ' . $change;
        }
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

    /** @param list<array<string, mixed>> $acl */
    private function describeAcl(array $acl): string
    {
        $fmt = function (array $values): string {
            return count($values) > 15 ? count($values) . ' itens' : implode(',', $values);
        };
        $lines = [];
        foreach ($acl as $i => $rule) {
            $lines[] = sprintf(
                '  %2d %s proto=%s dport=%s sport=%s sip=%s',
                $i + 1,
                $rule['policy'] ?? '?',
                implode(',', (array) ($rule['proto_list'] ?? [])) ?: 'any',
                $fmt((array) ($rule['dport_list'] ?? [])) ?: '(todas)',
                $fmt((array) ($rule['sport_list'] ?? [])) ?: '-',
                $fmt((array) ($rule['sip_list'] ?? [])) ?: '-',
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
     * Mergeable rules: no sip/sport filters. Collapsed per policy+proto (never mix TCP/UDP).
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
        $dport = $this->rulePorts($rule);

        // Empty dport = catch-all / protocol DROP (icmp, etc.) — keep in place for sync.
        if ($dport === []) {
            return false;
        }

        return $sip === [] && $sport === [];
    }

    /**
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
     *
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
     *
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
     *
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
