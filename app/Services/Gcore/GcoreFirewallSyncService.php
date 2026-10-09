<?php

namespace Pterodactyl\Services\Gcore;

use Pterodactyl\Models\Node;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Exceptions\DisplayException;

class GcoreFirewallSyncService
{
    /**
     * Sync ACL ports on Gcore for every distinct IP on the node that has (or had)
     * protected allocations — clears the managed rule when no ports remain.
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

        $targets = Allocation::query()
            ->where('node_id', $node->id)
            ->where('gcore_protected', true)
            ->distinct()
            ->pluck('ip')
            ->all();

        if ($targets === []) {
            return ['Nenhuma allocation protegida para sincronizar.'];
        }

        $lines = [];
        foreach ($targets as $ip) {
            $lines[] = $this->syncIp($node, (string) $ip);
        }

        return $lines;
    }

    /**
     * Ensure the Gcore profile for $ip has a managed ACL rule with exactly the
     * protected allocation ports of this node (other ACL rules are preserved).
     *
     * @throws DisplayException
     */
    public function syncIp(Node $node, string $ip): string
    {
        if (!$node->gcore_enabled) {
            throw new DisplayException('Este node não está marcado como Gcore.');
        }

        if ((string) config('gcore.api_key', '') === '') {
            throw new DisplayException('Configure GCORE_API_KEY no .env.');
        }

        $ports = Allocation::query()
            ->where('node_id', $node->id)
            ->where('ip', $ip)
            ->where('gcore_protected', true)
            ->orderBy('port')
            ->pluck('port')
            ->map(fn ($p) => (int) $p)
            ->unique()
            ->values()
            ->all();

        try {
            $client = GcoreClient::fromConfig();
            $profile = $this->findProfileByIp($client, $ip);
            if ($profile === null) {
                throw new DisplayException("Nenhum perfil Gcore encontrado para o IP {$ip}.");
            }

            $form = GcoreAclHelper::extractProfileFormData($profile);
            $policy = $this->resolvePolicy($node);
            $acl = $this->mergeManagedRule($form['acl'], $policy, $ports);

            $payload = $client->buildUpdatePayload($profile, $form['rate'], $form['geoip'], $acl);
            $updated = $client->updateProfile((int) $profile['id'], $payload);
            $status = (string) ($updated['status']['status'] ?? 'OK');

            $portLabel = $ports === [] ? 'nenhuma porta' : implode(', ', $ports);

            return "IP {$ip} · perfil {$profile['id']} · {$portLabel} · {$status}";
        } catch (DisplayException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DisplayException('Gcore sync falhou para ' . $ip . ': ' . $e->getMessage(), $e);
        }
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

    private function resolvePolicy(Node $node): string
    {
        $policy = trim((string) ($node->gcore_policy ?: config('gcore.default_policy', 'allowlist')));
        if (!in_array($policy, GcoreClient::POLICIES, true)) {
            return 'allowlist';
        }

        return $policy;
    }

    /**
     * Replace or create the single panel-managed ACL rule; keep all other rules.
     *
     * @param list<array<string, mixed>> $acl
     * @param list<int> $ports
     * @return list<array<string, mixed>>
     */
    private function mergeManagedRule(array $acl, string $policy, array $ports): array
    {
        $kept = [];
        foreach ($acl as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if ($this->isManagedRule($rule, $policy)) {
                continue;
            }
            $kept[] = $rule;
        }

        if ($ports !== []) {
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
    private function isManagedRule(array $rule, string $policy): bool
    {
        if ((string) ($rule['policy'] ?? '') !== $policy) {
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

        // Panel-managed rules always use proto "any" only.
        return $proto === ['any'] || $proto === ['ANY'];
    }
}
