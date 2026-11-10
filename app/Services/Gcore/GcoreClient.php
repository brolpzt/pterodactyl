<?php

namespace Pterodactyl\Services\Gcore;

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
            throw new \RuntimeException('Configure GCORE_API_KEY no arquivo .env');
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

        $profiles = array_is_list($data) ? $data : [$data];
        $this->cache()->putList($profiles);

        return $profiles;
    }

    /** @return array<string, mixed> */
    public function getProfile(int $id): array
    {
        $data = $this->request('GET', "/security/iaas/v2/profiles/$id");
        if (!is_array($data)) {
            throw new \RuntimeException('Resposta inválida da API.');
        }
        $this->cache()->put($data);

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function updateProfile(int $id, array $payload): array
    {
        try {
            $data = $this->request('PUT', "/security/iaas/v2/profiles/$id", $payload);
        } catch (\Throwable $e) {
            // The PUT may or may not have landed: the cached copy can no longer be trusted.
            $this->cache()->forget($id);

            throw $e;
        }
        if (!is_array($data)) {
            $this->cache()->forget($id);

            throw new \RuntimeException('Resposta inválida ao salvar.');
        }
        $this->cache()->applyUpdate($id, $payload, $data);

        return $data;
    }

    /**
     * PUT that survives an unknown outcome: when Gcore answers 5xx or the request times out,
     * the profile is re-read (a few times, the change may still be processing) and compared
     * with what was sent. Returns normally only when the change is confirmed on Gcore.
     *
     * @param array<string, mixed> $current profile as read right before building $payload
     * @param array<string, mixed> $payload
     *
     * @return array{response: array<string, mixed>, recovered: string|null} recovered = note when confirmed by re-read
     */
    public function updateProfileChecked(int $id, array $current, array $payload): array
    {
        try {
            return ['response' => $this->updateProfile($id, $payload), 'recovered' => null];
        } catch (\Throwable $e) {
            $expected = GcoreProfileCache::withPayload($current, $payload);
            if (!GcoreApiException::outcomeUnknown($e) || $expected === null) {
                throw $e;
            }
            $error = $e instanceof GcoreApiException ? 'HTTP ' . $e->status : 'sem resposta (timeout)';
        }

        $want = GcoreProfileCache::formHash($expected);
        $before = GcoreProfileCache::formHash($current);
        $attempts = max(1, (int) config('gcore.recheck_attempts', 3));
        $wait = max(0, (int) config('gcore.recheck_wait_seconds', 5));
        $state = 'unread';
        for ($i = 0; $i < $attempts; ++$i) {
            if ($i > 0 && $wait > 0) {
                sleep($wait);
            }
            try {
                $fresh = $this->getProfile($id);
            } catch (\Throwable) {
                continue;
            }
            $hash = GcoreProfileCache::formHash($fresh);
            if ($hash === $want) {
                return ['response' => $fresh, 'recovered' => "a Gcore respondeu {$error}, mas a releitura confirmou que a mudança foi aplicada"];
            }
            $state = $hash === $before ? 'unchanged' : 'different';
            if ($state === 'different') {
                break;
            }
        }

        throw new \RuntimeException(match ($state) {
            'unchanged' => "A Gcore respondeu {$error} e o perfil relido {$attempts}x continua igual ao anterior: a mudança NÃO foi aplicada (ou ainda está processando). Confira o perfil em alguns minutos antes de salvar de novo.", 'different' => "A Gcore respondeu {$error} e o perfil relido não é nem o anterior nem o enviado (aplicado em parte ou alterado por outra fonte). Confira o perfil antes de salvar de novo.", default => "A Gcore respondeu {$error} e não foi possível reler o perfil para saber se a mudança foi aplicada. Confira o perfil em alguns minutos.",
        });
    }

    /**
     * @param list<array<string, mixed>> $acl
     * @param array<string, mixed> $profile
     * @param array{low:int,medium:int,high:int,geo:int} $rateLimiters
     * @param list<string> $geoip
     *
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
     *
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
            throw new \RuntimeException('Resposta inválida do event log Gcore.');
        }

        return $data;
    }

    private function cache(): GcoreProfileCache
    {
        return app(GcoreProfileCache::class);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, scalar|null> $query
     *
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
                : trim(preg_replace('/\s+/', ' ', strip_tags($response->body())) ?? '');

            throw new GcoreApiException($response->status(), 'API HTTP ' . $response->status() . ': ' . $msg);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
