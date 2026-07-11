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
            ->where('is_companion', false)
            ->with('zone')
            ->orderByDesc('created_at')
            ->get();

        $allocation = $server->allocation;
        $visibleCount = $records->count();

        return [
            'records' => $records,
            'meta' => [
                'enabled' => true,
                'allowed_types' => $profile->allowed_types ?? [EggDnsProfile::TYPE_A],
                'default_type' => $profile->default_type,
                'max_records' => $profile->max_records_per_server,
                'zones' => $zones->map(fn (CloudflareZone $zone) => [
                    'id' => $zone->id,
                    'label' => $zone->label,
                    'domain' => $zone->publicDomain(),
                    'zone_domain' => $zone->domain,
                ])->values()->all(),
                'can_create' => $zones->isNotEmpty() && $visibleCount < $profile->max_records_per_server,
                'primary_ip' => $allocation?->ip,
                'primary_port' => $allocation?->port,
                'primary_alias' => $allocation?->ip_alias,
                'srv' => [
                    'service' => $profile->srvService(),
                    'protocol' => $profile->srvProtocol(),
                    'priority' => $profile->srv_priority,
                    'weight' => $profile->srv_weight,
                ],
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

        $type = strtoupper($profile->default_type);
        if (!$profile->allowsType($type)) {
            $allowed = $profile->allowed_types ?? [EggDnsProfile::TYPE_A];
            $type = strtoupper($allowed[0]);
        }

        $subdomain = strtolower(trim($data['subdomain']));
        $zone = $this->resolveZone((int) $data['zone_id']);

        $this->assertCanCreate($server, $profile, $type, $subdomain, $zone);

        $proxied = false;
        $srvPayload = null;
        $recordName = $zone->cloudflareRecordName($subdomain);
        $content = '';

        if ($type === EggDnsProfile::TYPE_SRV) {
            $this->ensureCompanionARecord($server, $user, $zone, $subdomain, $apiToken);
            $srvPayload = $this->buildSrvPayload($server, $profile, $subdomain, $zone);
            $recordName = $srvPayload['name'];
            $content = $srvPayload['content'];
        } else {
            $content = $this->resolveContent($server, $type, $data['content'] ?? null);
            $proxied = $type === EggDnsProfile::TYPE_CNAME
                ? (bool) ($data['proxied'] ?? $zone->default_proxied)
                : false;
            $recordName = $zone->cloudflareRecordName($subdomain);
        }

        $remote = $this->apiService->createDnsRecord(
            $apiToken,
            $zone->zone_id,
            $type,
            $recordName,
            $content,
            1,
            $proxied,
            $srvPayload['data'] ?? null
        );

        $attributes = [
            'server_id' => $server->id,
            'zone_id' => $zone->id,
            'cloudflare_record_id' => $remote['id'],
            'type' => $type,
            'subdomain' => $subdomain,
            'name' => $remote['name'] ?? ($srvPayload['full_name'] ?? $zone->recordFqdn($subdomain)),
            'content' => $content,
            'ttl' => (int) ($remote['ttl'] ?? 1),
            'proxied' => (bool) ($remote['proxied'] ?? $proxied),
            'is_companion' => false,
            'created_by' => $user->id,
        ];

        if ($type === EggDnsProfile::TYPE_SRV && $srvPayload) {
            $attributes = array_merge($attributes, [
                'srv_service' => $srvPayload['data']['service'],
                'srv_protocol' => $srvPayload['data']['proto'],
                'srv_port' => $srvPayload['data']['port'],
                'srv_priority' => $srvPayload['data']['priority'],
                'srv_weight' => $srvPayload['data']['weight'],
            ]);
        }

        return CloudflareDnsRecord::create($attributes);
    }

    /**
     * @throws DisplayException
     */
    public function deleteForServer(Server $server, CloudflareDnsRecord $record): void
    {
        if ($record->server_id !== $server->id) {
            throw new DisplayException('Este registro DNS não pertence a este servidor.');
        }

        if ($record->is_companion) {
            throw new DisplayException('Este registro DNS auxiliar não pode ser removido diretamente.');
        }

        $account = $this->resolveAccount();
        $apiToken = $this->decryptToken($account);
        $zone = $record->zone;

        if ($record->type === EggDnsProfile::TYPE_SRV) {
            $this->deleteCompanionARecord($server, $zone, $record->subdomain, $apiToken);
        }

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

    /**
     * @return array{name: string, full_name: string, content: string, data: array<string, mixed>}
     *
     * @throws DisplayException
     */
    public function buildSrvPayload(
        Server $server,
        EggDnsProfile $profile,
        string $subdomain,
        CloudflareZone $zone
    ): array {
        $service = $profile->srvService();
        $protocol = $profile->srvProtocol();
        $priority = $profile->srv_priority;
        $weight = $profile->srv_weight;
        $port = $this->resolveAllocationPort($server);
        $target = $this->resolveSrvTarget($zone, $subdomain);
        $relativeName = $zone->cloudflareRecordName($subdomain);

        $recordName = "{$service}.{$protocol}.{$relativeName}";
        $fullName = "{$recordName}.{$zone->domain}";
        $content = "{$priority} {$weight} {$port} {$target}";

        return [
            'name' => $recordName,
            'full_name' => $fullName,
            'content' => $content,
            'data' => [
                'service' => $service,
                'proto' => $protocol,
                'name' => $relativeName,
                'priority' => $priority,
                'weight' => $weight,
                'port' => $port,
                'target' => $target,
            ],
        ];
    }

    /**
     * @throws DisplayException
     */
    public function resolveAllocationIp(Server $server): string
    {
        $ip = $server->allocation?->ip;
        if (!$ip) {
            throw new DisplayException('Não foi possível determinar o IP da alocação primária deste servidor.');
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new DisplayException('A alocação primária deste servidor não possui um endereço IPv4 válido para registros do tipo A.');
        }

        return $ip;
    }

    /**
     * @throws DisplayException
     */
    public function resolveAllocationPort(Server $server): int
    {
        $port = $server->allocation?->port;
        if (!$port) {
            throw new DisplayException('Não foi possível determinar a porta da alocação primária deste servidor.');
        }

        return (int) $port;
    }

    /**
     * Cloudflare exige hostname como target SRV — usamos o FQDN público do registro.
     */
    public function resolveSrvTarget(CloudflareZone $zone, string $subdomain): string
    {
        return $zone->recordFqdn($subdomain);
    }

    /**
     * @throws DisplayException
     */
    private function ensureCompanionARecord(
        Server $server,
        User $user,
        CloudflareZone $zone,
        string $subdomain,
        string $apiToken
    ): CloudflareDnsRecord {
        $existing = CloudflareDnsRecord::query()
            ->where('server_id', $server->id)
            ->where('zone_id', $zone->id)
            ->where('subdomain', $subdomain)
            ->where('type', EggDnsProfile::TYPE_A)
            ->first();

        $ip = $this->resolveAllocationIp($server);
        $recordName = $zone->cloudflareRecordName($subdomain);
        $fqdn = $zone->recordFqdn($subdomain);

        if ($existing) {
            if ($existing->content !== $ip) {
                $remote = $this->apiService->updateDnsRecord(
                    $apiToken,
                    $zone->zone_id,
                    $existing->cloudflare_record_id,
                    EggDnsProfile::TYPE_A,
                    $existing->name,
                    $ip,
                    $existing->ttl,
                    false
                );

                $existing->update([
                    'content' => $ip,
                    'name' => $remote['name'] ?? $existing->name,
                ]);
            }

            return $existing;
        }

        $remote = $this->apiService->createDnsRecord(
            $apiToken,
            $zone->zone_id,
            EggDnsProfile::TYPE_A,
            $recordName,
            $ip,
            1,
            false
        );

        return CloudflareDnsRecord::create([
            'server_id' => $server->id,
            'zone_id' => $zone->id,
            'cloudflare_record_id' => $remote['id'],
            'type' => EggDnsProfile::TYPE_A,
            'subdomain' => $subdomain,
            'name' => $remote['name'] ?? $fqdn,
            'content' => $ip,
            'ttl' => (int) ($remote['ttl'] ?? 1),
            'proxied' => false,
            'is_companion' => true,
            'created_by' => $user->id,
        ]);
    }

    private function deleteCompanionARecord(
        Server $server,
        CloudflareZone $zone,
        string $subdomain,
        string $apiToken
    ): void {
        $companion = CloudflareDnsRecord::query()
            ->where('server_id', $server->id)
            ->where('zone_id', $zone->id)
            ->where('subdomain', $subdomain)
            ->where('type', EggDnsProfile::TYPE_A)
            ->where('is_companion', true)
            ->first();

        if (!$companion) {
            return;
        }

        $this->apiService->deleteDnsRecord($apiToken, $zone->zone_id, $companion->cloudflare_record_id);
        $companion->delete();
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

        if (CloudflareDnsRecord::query()
            ->where('zone_id', $zone->id)
            ->where('subdomain', $subdomain)
            ->where('type', $type)
            ->where('is_companion', false)
            ->exists()
        ) {
            throw new DisplayException('Este subdomínio já está em uso neste domínio para o tipo selecionado.');
        }

        $count = CloudflareDnsRecord::query()
            ->where('server_id', $server->id)
            ->where('is_companion', false)
            ->count();

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
            return $this->resolveAllocationIp($server);
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
}
