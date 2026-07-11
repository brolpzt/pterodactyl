<?php

namespace Pterodactyl\Services\Cloudflare;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\EggDnsProfile;
use Pterodactyl\Models\CloudflareZone;
use Pterodactyl\Models\CloudflareAccount;
use Pterodactyl\Models\CloudflareDnsRecord;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Contracts\Encryption\Encrypter;

class CloudflareDnsService
{
    private const RESERVED_SUBDOMAINS = [
        'www', 'mail', 'panel', 'admin', 'api', 'ftp', 'smtp', 'pop', 'imap',
        'ns1', 'ns2', 'autodiscover', 'autoconfig', '_dmarc', '_domainkey',
    ];

    public function __construct(
        private CloudflareApiService $apiService,
        private Encrypter $encrypter,
    ) {
    }

    /**
     * @return array{records: \Illuminate\Support\Collection, meta: array<string, mixed>}
     *
     * @throws DisplayException
     */
    public function listForServer(Server $server): array
    {
        $profile = $this->resolveDnsProfile($server);
        $zones = $this->availableZones();
        $records = CloudflareDnsRecord::query()
            ->where('server_id', $server->id)
            ->with('zone')
            ->orderByDesc('created_at')
            ->get();

        return [
            'records' => $records,
            'meta' => [
                'enabled' => true,
                'allowed_types' => $profile->allowed_types ?? [EggDnsProfile::TYPE_A],
                'default_type' => $profile->default_type,
                'max_records' => $profile->max_records_per_server,
                'zones' => $zones->map(fn (CloudflareZone $zone) => [
                    'id' => $zone->id,
                    'domain' => $zone->domain,
                ])->values()->all(),
                'can_create' => $zones->isNotEmpty() && $records->count() < $profile->max_records_per_server,
                'primary_ip' => $server->allocation?->ip,
            ],
        ];
    }

    /**
     * @throws DisplayException
     */
    public function createForServer(Server $server, User $user, array $data): CloudflareDnsRecord
    {
        $profile = $this->resolveDnsProfile($server);
        $account = $this->resolveAccount();
        $apiToken = $this->decryptToken($account);

        $type = strtoupper($data['type']);
        $subdomain = strtolower(trim($data['subdomain']));
        $zone = $this->resolveZone((int) $data['zone_id']);

        $this->assertCanCreate($server, $profile, $type, $subdomain, $zone);

        $content = $this->resolveContent($server, $type, $data['content'] ?? null);
        $proxied = $type === EggDnsProfile::TYPE_CNAME ? (bool) ($data['proxied'] ?? $zone->default_proxied) : false;
        $recordName = $this->buildRecordName($subdomain, $zone->domain);

        $remote = $this->apiService->createDnsRecord(
            $apiToken,
            $zone->zone_id,
            $type,
            $recordName,
            $content,
            1,
            $proxied
        );

        return CloudflareDnsRecord::create([
            'server_id' => $server->id,
            'zone_id' => $zone->id,
            'cloudflare_record_id' => $remote['id'],
            'type' => $type,
            'subdomain' => $subdomain,
            'name' => $remote['name'] ?? $recordName,
            'content' => $content,
            'ttl' => (int) ($remote['ttl'] ?? 1),
            'proxied' => (bool) ($remote['proxied'] ?? $proxied),
            'created_by' => $user->id,
        ]);
    }

    /**
     * @throws DisplayException
     */
    public function deleteForServer(Server $server, CloudflareDnsRecord $record): void
    {
        if ($record->server_id !== $server->id) {
            throw new DisplayException('Este registro DNS não pertence a este servidor.');
        }

        $account = $this->resolveAccount();
        $apiToken = $this->decryptToken($account);
        $zone = $record->zone;

        $this->apiService->deleteDnsRecord($apiToken, $zone->zone_id, $record->cloudflare_record_id);
        $record->delete();
    }

    /**
     * @throws DisplayException
     */
    public function resolveDnsProfile(Server $server): EggDnsProfile
    {
        $features = $server->egg->inherit_features ?? [];
        if (!in_array(\Pterodactyl\Models\Egg::FEATURE_DNS, $features, true)) {
            throw new DisplayException('DNS não está habilitado para este tipo de servidor.');
        }

        $profile = $server->egg->dnsProfile;
        if (!$profile || !$profile->enabled) {
            throw new DisplayException('DNS não está configurado para este tipo de servidor.');
        }

        return $profile;
    }

    private function resolveAccount(): CloudflareAccount
    {
        $account = CloudflareAccount::query()->first();
        if (!$account || !$account->isConfigured()) {
            throw new DisplayException('A integração Cloudflare ainda não foi configurada pelo administrador.');
        }

        return $account;
    }

    private function decryptToken(CloudflareAccount $account): string
    {
        try {
            return $this->encrypter->decrypt($account->api_token);
        } catch (\Throwable) {
            throw new DisplayException('Não foi possível ler o token da API Cloudflare. Reconfigure a integração no painel administrativo.');
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, CloudflareZone>
     */
    private function availableZones()
    {
        return CloudflareZone::query()
            ->where('is_active', true)
            ->where('allow_user_create', true)
            ->orderBy('domain')
            ->get();
    }

    /**
     * @throws DisplayException
     */
    private function resolveZone(int $zoneId): CloudflareZone
    {
        $zone = CloudflareZone::query()
            ->where('id', $zoneId)
            ->where('is_active', true)
            ->where('allow_user_create', true)
            ->first();

        if (!$zone) {
            throw new DisplayException('O domínio selecionado não está disponível para criação de DNS.');
        }

        return $zone;
    }

    /**
     * @throws DisplayException
     */
    private function assertCanCreate(
        Server $server,
        EggDnsProfile $profile,
        string $type,
        string $subdomain,
        CloudflareZone $zone
    ): void {
        if (!$profile->allowsType($type)) {
            throw new DisplayException("O tipo de registro DNS \"{$type}\" não é permitido para este servidor.");
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain)) {
            throw new DisplayException('O subdomínio informado é inválido. Use apenas letras minúsculas, números e hífens.');
        }

        if (in_array($subdomain, self::RESERVED_SUBDOMAINS, true)) {
            throw new DisplayException('Este subdomínio está reservado e não pode ser utilizado.');
        }

        if (CloudflareDnsRecord::query()->where('zone_id', $zone->id)->where('subdomain', $subdomain)->exists()) {
            throw new DisplayException('Este subdomínio já está em uso neste domínio.');
        }

        $count = CloudflareDnsRecord::query()->where('server_id', $server->id)->count();
        if ($count >= $profile->max_records_per_server) {
            throw new DisplayException('Este servidor já atingiu o limite máximo de registros DNS.');
        }
    }

    /**
     * @throws DisplayException
     */
    private function resolveContent(Server $server, string $type, ?string $content): string
    {
        if ($type === EggDnsProfile::TYPE_A) {
            $ip = $server->allocation?->ip;
            if (!$ip) {
                throw new DisplayException('Não foi possível determinar o IP da alocação primária deste servidor.');
            }

            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                throw new DisplayException('A alocação primária deste servidor não possui um endereço IPv4 válido para registros do tipo A.');
            }

            return $ip;
        }

        $content = trim((string) $content);
        if ($content === '') {
            throw new DisplayException('Informe o destino (hostname) para o registro CNAME.');
        }

        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $content)) {
            throw new DisplayException('O destino do CNAME deve ser um hostname válido.');
        }

        return rtrim($content, '.');
    }

    private function buildRecordName(string $subdomain, string $domain): string
    {
        return "{$subdomain}.{$domain}";
    }
}
