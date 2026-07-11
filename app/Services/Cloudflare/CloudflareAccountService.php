<?php

namespace Pterodactyl\Services\Cloudflare;

use Pterodactyl\Models\CloudflareAccount;
use Pterodactyl\Models\CloudflareZone;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Contracts\Encryption\Encrypter;

class CloudflareAccountService
{
    public function __construct(
        private CloudflareApiService $apiService,
        private Encrypter $encrypter,
    ) {
    }

    /**
     * @throws DisplayException
     */
    public function saveAccount(array $data): CloudflareAccount
    {
        $account = CloudflareAccount::query()->first() ?? new CloudflareAccount();

        $apiToken = $data['api_token'] ?? null;
        if (!empty($apiToken)) {
            $this->apiService->verifyToken($apiToken);
            $account->api_token = $this->encrypter->encrypt($apiToken);
        } elseif (!$account->exists || !$account->isConfigured()) {
            throw new DisplayException('Informe um token de API Cloudflare válido.');
        }

        $account->name = $data['name'] ?? 'Cloudflare';
        $account->save();

        return $account;
    }

    /**
     * @throws DisplayException
     */
    public function createZone(CloudflareAccount $account, array $data): CloudflareZone
    {
        $domain = strtolower(trim($data['domain']));
        $apiToken = $this->decryptToken($account);
        $zoneId = $this->apiService->resolveZoneId($apiToken, $domain);

        return CloudflareZone::create([
            'cloudflare_account_id' => $account->id,
            'zone_id' => $zoneId,
            'domain' => $domain,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'allow_user_create' => (bool) ($data['allow_user_create'] ?? true),
            'default_proxied' => (bool) ($data['default_proxied'] ?? false),
        ]);
    }

    /**
     * @throws DisplayException
     */
    public function updateZone(CloudflareZone $zone, array $data): CloudflareZone
    {
        if (!empty($data['domain']) && strtolower(trim($data['domain'])) !== $zone->domain) {
            $apiToken = $this->decryptToken($zone->account);
            $zone->domain = strtolower(trim($data['domain']));
            $zone->zone_id = $this->apiService->resolveZoneId($apiToken, $zone->domain);
        }

        $zone->fill([
            'is_active' => (bool) ($data['is_active'] ?? $zone->is_active),
            'allow_user_create' => (bool) ($data['allow_user_create'] ?? $zone->allow_user_create),
            'default_proxied' => (bool) ($data['default_proxied'] ?? $zone->default_proxied),
        ])->save();

        return $zone->refresh();
    }

    public function deleteZone(CloudflareZone $zone): void
    {
        if ($zone->dnsRecords()->exists()) {
            throw new DisplayException('Não é possível remover um domínio que ainda possui registros DNS associados.');
        }

        $zone->delete();
    }

    /**
     * @throws DisplayException
     */
    private function decryptToken(CloudflareAccount $account): string
    {
        if (!$account->isConfigured()) {
            throw new DisplayException('Configure o token da API Cloudflare antes de gerenciar domínios.');
        }

        try {
            return $this->encrypter->decrypt($account->api_token);
        } catch (\Throwable) {
            throw new DisplayException('Não foi possível ler o token da API Cloudflare.');
        }
    }
}
