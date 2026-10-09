<?php

declare(strict_types=1);

final class GcoreClient
{
    private const BASE = 'https://api.gcore.com';

    /** @var list<string> */
    public const POLICIES = [
        'DROP',
        'minecraft',
        'counter-strike-16',
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
        'geo',
        'tcp-server',
        'dns-trust',
        'allowlist',
        'ratelimiter-low',
        'ratelimiter-medium',
        'ratelimiter-high',
    ];

    public const PROTOCOLS = ['any', 'tcp', 'udp', 'icmp', 'gre', 'esp'];

    public function __construct(private readonly string $apiKey)
    {
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

    /** @return array<string, mixed>|list<mixed> */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init(self::BASE . $path);
        if ($ch === false) {
            throw new RuntimeException('curl_init falhou.');
        }

        $headers = [
            'Authorization: apikey ' . $this->apiKey,
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
        ]);

        if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('Erro de rede: ' . $err);
        }

        $decoded = json_decode($raw, true);
        if ($code >= 400) {
            $msg = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['exception_class'] ?? $raw)
                : $raw;
            throw new RuntimeException("API HTTP $code: $msg");
        }

        return is_array($decoded) ? $decoded : [];
    }
}
