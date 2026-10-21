<?php

namespace Pterodactyl\Services\Firewall\Providers;

use RuntimeException;
use Pterodactyl\Models\Node;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Contracts\Firewall\FirewallAccessProvider;

class VultrFirewallAccessProvider implements FirewallAccessProvider
{
    private const BASE = 'https://api.vultr.com/v2';

    public function __construct(private Encrypter $encrypter)
    {
    }

    public function key(): string
    {
        return 'vultr';
    }

    public function isConfigured(Node $node): bool
    {
        return $node->sftp_firewall_provider === $this->key()
            && filled($node->sftp_firewall_group_id)
            && filled($node->sftp_firewall_api_key);
    }

    public function grant(Node $node, string $clientIp, int $port, ?string $notes = null): string
    {
        $groupId = (string) $node->sftp_firewall_group_id;
        $isV6 = (bool) filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        $payload = [
            'ip_type' => $isV6 ? 'v6' : 'v4',
            'protocol' => 'tcp',
            // Vultr expects the address itself; /32 (v4) or /128 (v6) for a single host.
            'subnet' => $clientIp,
            'subnet_size' => $isV6 ? 128 : 32,
            'port' => (string) $port,
            'notes' => $notes ?: 'Pterodactyl SFTP temporary access',
        ];

        $data = $this->request($node, 'POST', "/firewalls/{$groupId}/rules", $payload);
        $ruleId = (string) data_get($data, 'firewall_rule.id', data_get($data, 'id', ''));

        if ($ruleId === '') {
            throw new RuntimeException('Vultr API did not return a firewall rule id.');
        }

        return $ruleId;
    }

    public function revoke(Node $node, string $externalRuleId): void
    {
        $groupId = (string) $node->sftp_firewall_group_id;
        $this->request($node, 'DELETE', "/firewalls/{$groupId}/rules/{$externalRuleId}");
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(Node $node, string $method, string $path, ?array $payload = null): array
    {
        $apiKey = $this->decryptApiKey($node);
        if ($apiKey === '') {
            throw new RuntimeException('Vultr API key is not configured for this node.');
        }

        $pending = Http::baseUrl(self::BASE)
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(20)
            ->asJson();

        $response = match (strtoupper($method)) {
            'POST' => $pending->post($path, $payload ?? []),
            'DELETE' => $pending->delete($path),
            'GET' => $pending->get($path),
            default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };

        if ($response->status() === 204) {
            return [];
        }

        if (!$response->successful()) {
            throw new RuntimeException(sprintf(
                'Vultr API error HTTP %d: %s',
                $response->status(),
                $response->body()
            ));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function decryptApiKey(Node $node): string
    {
        $raw = (string) ($node->sftp_firewall_api_key ?? '');
        if ($raw === '') {
            return '';
        }

        try {
            return $this->encrypter->decrypt($raw);
        } catch (\Throwable) {
            // Allow plaintext during transition / misconfigured installs.
            return $raw;
        }
    }
}
