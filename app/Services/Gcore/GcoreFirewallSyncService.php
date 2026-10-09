<?php

namespace Pterodactyl\Services\Gcore;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\NodeGcoreIp;
use Pterodactyl\Exceptions\DisplayException;

class GcoreFirewallSyncService
{
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
     * Ensure the Gcore profile for $ip has managed ACL rules grouped by egg policy.
     * Other ACL rules (manual) are preserved.
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

        // If the IP is no longer marked Gcore, push an empty port set to clear managed rules.
        $portsByPolicy = $ipIsGcore ? $this->collectPortsByPolicy($node, $ip) : [];

        try {
            $client = GcoreClient::fromConfig();
            $profile = $this->findProfileByIp($client, $ip);
            if ($profile === null) {
                throw new DisplayException("Nenhum perfil Gcore encontrado para o IP {$ip}.");
            }

            $form = GcoreAclHelper::extractProfileFormData($profile);
            $acl = $this->mergeManagedRules($form['acl'], $portsByPolicy);

            $payload = $client->buildUpdatePayload($profile, $form['rate'], $form['geoip'], $acl);
            $updated = $client->updateProfile((int) $profile['id'], $payload);
            $status = (string) ($updated['status']['status'] ?? 'OK');

            if ($portsByPolicy === []) {
                $summary = $ipIsGcore ? 'nenhuma porta' : 'IP fora do Gcore (regras gerenciadas limpas)';
            } else {
                $parts = [];
                foreach ($portsByPolicy as $policy => $ports) {
                    $parts[] = $policy . '[' . implode(',', $ports) . ']';
                }
                $summary = implode(' · ', $parts);
            }

            return "IP {$ip} · perfil {$profile['id']} · {$summary} · {$status}";
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
            ->with(['server.egg:id,gcore_policy'])
            ->orderBy('port')
            ->get();

        /** @var array<string, array<int, true>> $map */
        $map = [];
        foreach ($allocations as $allocation) {
            $policy = $this->normalizePolicy(
                $allocation->server?->egg?->gcore_policy
            );
            $map[$policy][(int) $allocation->port] = true;
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
     * Drop previous panel-managed rules (empty sip/sport, proto any, known policy)
     * and recreate one rule per egg policy that still has ports.
     *
     * @param list<array<string, mixed>> $acl
     * @param array<string, list<int>> $portsByPolicy
     * @return list<array<string, mixed>>
     */
    private function mergeManagedRules(array $acl, array $portsByPolicy): array
    {
        $kept = [];
        foreach ($acl as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if ($this->isManagedRule($rule)) {
                continue;
            }
            $kept[] = $rule;
        }

        foreach ($portsByPolicy as $policy => $ports) {
            if ($ports === []) {
                continue;
            }
            $kept[] = [
                'policy' => $policy,
                'sip_list' => [],
                'dport_list' => array_values($ports),
                'proto_list' => ['any'],
                'sport_list' => [],
            ];
        }

        return array_values($kept);
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function isManagedRule(array $rule): bool
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

        return $proto === ['any'] || $proto === ['ANY'];
    }
}
