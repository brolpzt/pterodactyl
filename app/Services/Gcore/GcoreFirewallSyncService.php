<?php

namespace Pterodactyl\Services\Gcore;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\NodeGcoreIp;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Support\Facades\Log;

class GcoreFirewallSyncService
{
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
        $portsByPolicy = $ipIsGcore ? $this->collectPortsByPolicy($node, $ip) : [];

        try {
            $client = GcoreClient::fromConfig();
            $profile = $this->findProfileByIp($client, $ip);
            if ($profile === null) {
                throw new DisplayException("Nenhum perfil Gcore encontrado para o IP {$ip}.");
            }

            $form = GcoreAclHelper::extractProfileFormData($profile);
            $merge = $this->mergeGroupedAcl($form['acl'], $portsByPolicy);

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
     * @return array<string, list<int>> policy => sorted unique ports
     */
    private function collectPortsByPolicy(Node $node, string $ip): array
    {
        $allocations = Allocation::query()
            ->where('node_id', $node->id)
            ->where('ip', $ip)
            ->where('gcore_protected', true)
            ->whereNotNull('server_id')
            ->with(['server.egg:id,gcore_policy'])
            ->orderBy('port')
            ->get();

        /** @var array<string, array<int, true>> $map */
        $map = [];
        foreach ($allocations as $allocation) {
            $port = (int) $allocation->port;
            if ($port < 1 || $port > 65535) {
                continue;
            }

            // Free allocations must never open ACL rules (would fall back to allowlist).
            if (!$allocation->server_id || !$allocation->server) {
                continue;
            }

            $rawPolicy = trim((string) ($allocation->server->egg?->gcore_policy ?? ''));
            if ($rawPolicy === '' || !in_array($rawPolicy, GcoreClient::POLICIES, true)) {
                // No explicit egg policy — skip instead of inventing allowlist.
                continue;
            }

            $map[$rawPolicy][$port] = true;
        }

        $out = [];
        foreach ($map as $policy => $ports) {
            $list = array_map('intval', array_keys($ports));
            sort($list);
            $out[$policy] = $list;
        }

        ksort($out);

        return $out;
    }

    /**
     * Rebuild ACL:
     * - preserve non-groupable (manual) rules
     * - group panel-managed rules by ACL policy (one rule per policy)
     * - never duplicate a port that already exists in a preserved rule of the same policy
     * - never duplicate a port inside the same grouped rule
     *
     * @param list<array<string, mixed>> $acl
     * @param array<string, list<int>> $desiredByPolicy ports the panel wants open
     * @return array{
     *   acl: list<array<string, mixed>>,
     *   added: array<string, list<int>>,
     *   removed: array<string, list<int>>,
     *   skipped_existing: array<string, list<int>>,
     * }
     */
    private function mergeGroupedAcl(array $acl, array $desiredByPolicy): array
    {
        $preserved = [];
        /** @var array<string, array<int, true>> $existingInPreserved policy => port set */
        $existingInPreserved = [];
        /** @var array<string, array<int, true>> $existingInGroupable previous grouped/managed ports */
        $existingInGroupable = [];

        foreach ($acl as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if ($this->isGroupableRule($rule)) {
                $policy = $this->normalizePolicy((string) ($rule['policy'] ?? ''));
                foreach ($this->rulePorts($rule) as $port) {
                    $existingInGroupable[$policy][$port] = true;
                }
                continue;
            }

            $policy = trim((string) ($rule['policy'] ?? ''));
            if ($policy === '') {
                $policy = $this->normalizePolicy(null);
            }
            foreach ($this->rulePorts($rule) as $port) {
                $existingInPreserved[$policy][$port] = true;
            }
            $preserved[] = $this->normalizeRuleShape($rule, $policy);
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

            $portsForRule = [];
            foreach ($desired as $port) {
                // Already open in a manual/preserved rule of the same ACL policy - do not duplicate.
                if (isset($existingInPreserved[$policy][$port])) {
                    $skipped[$policy][] = $port;
                    continue;
                }
                $portsForRule[$port] = true;
            }

            $finalPorts = array_map('intval', array_keys($portsForRule));
            sort($finalPorts);

            $added[$policy] = array_values(array_diff($finalPorts, $previous));
            $removed[$policy] = array_values(array_diff($previous, $finalPorts));

            if ($finalPorts === []) {
                continue;
            }

            $grouped[] = [
                'policy' => $policy,
                'sip_list' => [],
                'dport_list' => $finalPorts,
                'proto_list' => ['any'],
                'sport_list' => [],
            ];
        }

        // Drop empty policy keys from report arrays.
        $added = array_filter($added, fn ($ports) => $ports !== []);
        $removed = array_filter($removed, fn ($ports) => $ports !== []);
        $skipped = array_filter($skipped, fn ($ports) => $ports !== []);

        // Gcore evaluates ACL top→bottom (first match wins). Specific game rules
        // must sit above catch-all / default rules, otherwise they never apply.
        return [
            'acl' => array_values(array_merge($grouped, $preserved)),
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
     * Rules the panel owns/groups: empty sip/sport and proto "any".
     * Multiple such rules of the same policy are collapsed into one.
     *
     * @param array<string, mixed> $rule
     */
    private function isGroupableRule(array $rule): bool
    {
        $policy = (string) ($rule['policy'] ?? '');
        if (!in_array($policy, GcoreClient::POLICIES, true)) {
            return false;
        }

        $sip = $rule['sip_list'] ?? [];
        $sport = $rule['sport_list'] ?? [];
        $proto = $rule['proto_list'] ?? [];

        if (!is_array($sip) || !is_array($sport) || !is_array($proto)) {
            return false;
        }

        if ($sip !== [] || $sport !== []) {
            return false;
        }

        if ($proto === []) {
            return true;
        }

        $normalized = array_values(array_map(
            static fn ($p) => strtolower((string) $p),
            $proto
        ));

        return $normalized === ['any'];
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
