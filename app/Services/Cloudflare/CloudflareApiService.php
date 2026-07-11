<?php

namespace Pterodactyl\Services\Cloudflare;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;

class CloudflareApiService
{
    private const BASE_URL = 'https://api.cloudflare.com/client/v4';

    /**
     * Verifies that the provided API token is valid.
     *
     * @throws DisplayException
     */
    public function verifyToken(string $apiToken): void
    {
        $response = Http::withToken($apiToken)
            ->acceptJson()
            ->get(self::BASE_URL . '/user/tokens/verify');

        if (!$response->successful() || !($response->json('success') ?? false)) {
            throw new DisplayException('O token da API Cloudflare é inválido ou não possui as permissões necessárias.');
        }
    }

    /**
     * Resolves a zone ID from a domain name.
     *
     * @throws DisplayException
     */
    public function resolveZoneId(string $apiToken, string $domain): string
    {
        $response = Http::withToken($apiToken)
            ->acceptJson()
            ->get(self::BASE_URL . '/zones', [
                'name' => $domain,
                'status' => 'active',
            ]);

        if (!$response->successful() || !($response->json('success') ?? false)) {
            throw new DisplayException('Não foi possível consultar as zonas na Cloudflare: ' . $this->extractError($response->json()));
        }

        $zones = $response->json('result', []);
        foreach ($zones as $zone) {
            if (strcasecmp($zone['name'] ?? '', $domain) === 0) {
                return $zone['id'];
            }
        }

        throw new DisplayException("O domínio \"{$domain}\" não foi encontrado na conta Cloudflare configurada.");
    }

    /**
     * Creates a DNS record in Cloudflare.
     *
     * @return array<string, mixed>
     *
     * @throws DisplayException
     */
    public function createDnsRecord(
        string $apiToken,
        string $zoneId,
        string $type,
        string $name,
        string $content,
        int $ttl = 1,
        bool $proxied = false,
        ?array $srvData = null
    ): array {
        $payload = $this->buildPayload($type, $name, $content, $ttl, $proxied, $srvData);

        $response = Http::withToken($apiToken)
            ->acceptJson()
            ->post(self::BASE_URL . "/zones/{$zoneId}/dns_records", $payload);

        if (!$response->successful() || !($response->json('success') ?? false)) {
            throw new DisplayException('Não foi possível criar o registro DNS na Cloudflare: ' . $this->extractError($response->json()));
        }

        return $response->json('result');
    }

    /**
     * Updates an existing DNS record in Cloudflare.
     *
     * @return array<string, mixed>
     *
     * @throws DisplayException
     */
    public function updateDnsRecord(
        string $apiToken,
        string $zoneId,
        string $recordId,
        string $type,
        string $name,
        string $content,
        int $ttl = 1,
        bool $proxied = false,
        ?array $srvData = null
    ): array {
        $payload = $this->buildPayload($type, $name, $content, $ttl, $proxied, $srvData);

        $response = Http::withToken($apiToken)
            ->acceptJson()
            ->patch(self::BASE_URL . "/zones/{$zoneId}/dns_records/{$recordId}", $payload);

        if (!$response->successful() || !($response->json('success') ?? false)) {
            throw new DisplayException('Não foi possível atualizar o registro DNS na Cloudflare: ' . $this->extractError($response->json()));
        }

        return $response->json('result');
    }

    /**
     * Deletes a DNS record from Cloudflare.
     *
     * @throws DisplayException
     */
    public function deleteDnsRecord(string $apiToken, string $zoneId, string $recordId): void
    {
        $response = Http::withToken($apiToken)
            ->acceptJson()
            ->delete(self::BASE_URL . "/zones/{$zoneId}/dns_records/{$recordId}");

        if (!$response->successful() || !($response->json('success') ?? false)) {
            throw new DisplayException('Não foi possível remover o registro DNS na Cloudflare: ' . $this->extractError($response->json()));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        string $type,
        string $name,
        string $content,
        int $ttl,
        bool $proxied,
        ?array $srvData
    ): array {
        if ($type === 'SRV' && $srvData) {
            return [
                'type' => 'SRV',
                'name' => $name,
                'ttl' => $ttl,
                'data' => [
                    'service' => $srvData['service'],
                    'proto' => $srvData['proto'],
                    'name' => $srvData['name'],
                    'priority' => (int) $srvData['priority'],
                    'weight' => (int) $srvData['weight'],
                    'port' => (int) $srvData['port'],
                    'target' => $srvData['target'],
                ],
            ];
        }

        $payload = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
        ];

        if (in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
            $payload['proxied'] = $proxied;
        }

        return $payload;
    }

    private function extractError(?array $body): string
    {
        if (!$body) {
            return 'erro desconhecido';
        }

        $errors = $body['errors'] ?? [];
        if (empty($errors)) {
            return 'erro desconhecido';
        }

        return collect($errors)->pluck('message')->filter()->implode('; ') ?: 'erro desconhecido';
    }
}
