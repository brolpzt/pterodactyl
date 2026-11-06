<?php

namespace Pterodactyl\Services\Gcore;

use RuntimeException;
use Illuminate\Support\Facades\Http;

class GcoreClient
{
    private const BASE = 'https://api.gcore.com';

    /** @var list<string> */
    public const POLICIES = [
        'DROP',
        'minecraft',
        'counter-strike-16',
        'counter-strike-16-gold',
        'counter-strike-go',
        'counter-strike-2',
        'enet-udp',
        'raknet-engine',
        'unity-engine-unet',
        'unity-engine-utp',
        'grand-theft-auto',
        'team-speak',
        'rust',
        'battlefield2-query',
        'battlefield2',
        'udp-general',
        'geo',
        'tcp-server',
        'tcp-private',
        'dns-trust',
        'allowlist',
        'ratelimiter-low',
        'ratelimiter-medium',
        'ratelimiter-high',
    ];

    public const PROTOCOLS = ['any', 'tcp', 'udp', 'icmp', 'gre', 'esp'];

    public function __construct(private readonly string $apiKey)
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Configure GCORE_API_KEY no arquivo .env');
        }
    }

    public static function fromConfig(): self
    {
        return new self((string) config('gcore.api_key', ''));
    }

    /** @return list<array<string, mixed>> */
    public function listProfiles(): array
    {
        $data = $this->request('GET', '/security/iaas/v2/profiles');
        if (!is_array($data)) {
            return [];
        }

        return array_is_list($data) ? $data : [$data];
    }

    /** @return array<string, mixed> */
    public function getProfile(int $id): array
    {
        $data = $this->request('GET', "/security/iaas/v2/profiles/$id");
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida da API.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateProfile(int $id, array $payload): array
    {
        $data = $this->request('PUT', "/security/iaas/v2/profiles/$id", $payload);
        if (!is_array($data)) {
            throw new RuntimeException('Resposta inválida ao salvar.');
        }

        return $data;
    }

    /**
     * @param list<array<string, mixed>> $acl
     * @param array<string, mixed> $profile
     * @param array{low:int,medium:int,high:int,geo:int} $rateLimiters
     * @param list<string> $geoip
     * @return array<string, mixed>
     */
    public function buildUpdatePayload(array $profile, array $rateLimiters, array $geoip, array $acl): array
    {
        $templateId = (int) ($profile['profile_template']['id'] ?? 0);
        $fields = [];

        foreach ($profile['fields'] as $field) {
            $name = $field['name'] ?? '';
            $base = (int) ($field['base_field'] ?? 0);
            if ($name === 'Rate limiter low') {
                $fields[] = ['base_field' => $base, 'field_value' => (int) $rateLimiters['low']];
            } elseif ($name === 'Rate limiter medium') {
                $fields[] = ['base_field' => $base, 'field_value' => (int) $rateLimiters['medium']];
            } elseif ($name === 'Rate limiter high') {
                $fields[] = ['base_field' => $base, 'field_value' => (int) $rateLimiters['high']];
            } elseif ($name === 'Rate limiter geo') {
                $fields[] = ['base_field' => $base, 'field_value' => (int) $rateLimiters['geo']];
            } elseif ($name === 'GEOIP list') {
                $fields[] = ['base_field' => $base, 'field_value' => array_values($geoip)];
            } elseif ($name === 'ACL list') {
                $fields[] = ['base_field' => $base, 'field_value' => array_values($acl)];
            }
        }

        return [
            'id' => (int) $profile['id'],
            'profile_template' => $templateId,
            'ip_address' => (string) ($profile['ip_address'] ?? ''),
            'site' => (string) ($profile['site'] ?? ''),
            'fields' => $fields,
        ];
    }

    /**
     * List DDoS / RTBH event logs.
     *
     * @param array<string, scalar|null> $query
     * @return array{results?: list<array<string, mixed>>, count?: int, limit?: int, offset?: int}
     */
    public function listEventLogs(array $query = []): array
    {
        $data = $this->request('GET', '/security/notifier/v1/event_logs', null, $query);

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEventLog(string $eventId): array
    {
        $data = $this->request('GET', '/security/notifier/v1/event_logs/' . rawurlencode($eventId));
        if (!is_array($data) || !isset($data['id'])) {
            throw new RuntimeException('Resposta inválida do event log Gcore.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|list<mixed>
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $pending = Http::baseUrl(self::BASE)
            ->withHeaders([
                'Authorization' => 'apikey ' . $this->apiKey,
                'Accept' => 'application/json',
            ])
            ->timeout(60);

        if ($query !== []) {
            $pending = $pending->withQueryParameters(array_filter(
                $query,
                static fn ($value) => $value !== null && $value !== ''
            ));
        }

        $response = $body === null
            ? $pending->send($method, $path)
            : $pending->withBody(json_encode($body, JSON_THROW_ON_ERROR), 'application/json')->send($method, $path);

        $decoded = $response->json();
        if ($response->failed()) {
            $msg = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['exception_class'] ?? $response->body())
                : $response->body();

            throw new RuntimeException('API HTTP ' . $response->status() . ': ' . $msg);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
