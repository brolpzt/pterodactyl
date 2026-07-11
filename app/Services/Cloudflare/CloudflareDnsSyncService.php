<?php

namespace Pterodactyl\Services\Cloudflare;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\EggDnsProfile;
use Pterodactyl\Models\CloudflareAccount;
use Pterodactyl\Models\CloudflareDnsRecord;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;

class CloudflareDnsSyncService
{
    public function __construct(
        private CloudflareApiService $apiService,
        private CloudflareDnsService $dnsService,
        private Encrypter $encrypter,
    ) {
    }

    /**
     * Synchronizes existing DNS records when the primary allocation changes.
     */
    public function syncForServer(Server $server): void
    {
        try {
            $this->dnsService->resolveDnsProfile($server);
        } catch (DisplayException) {
            return;
        }

        $server->load(['allocation', 'egg.dnsProfile']);
        $records = CloudflareDnsRecord::query()
            ->where('server_id', $server->id)
            ->with('zone')
            ->get();

        if ($records->isEmpty()) {
            return;
        }

        $account = CloudflareAccount::query()->first();
        if (!$account || !$account->isConfigured()) {
            return;
        }

        try {
            $apiToken = $this->encrypter->decrypt($account->api_token);
        } catch (\Throwable) {
            Log::warning('Cloudflare DNS sync skipped: unable to decrypt API token.', [
                'server_id' => $server->id,
            ]);

            return;
        }

        $profile = $server->egg->dnsProfile;

        foreach ($records as $record) {
            try {
                $this->syncRecord($server, $record, $profile, $apiToken);
            } catch (\Throwable $exception) {
                Log::error('Failed to sync Cloudflare DNS record: ' . $exception->getMessage(), [
                    'server_id' => $server->id,
                    'record_id' => $record->id,
                ]);
            }
        }
    }

    /**
     * @throws DisplayException
     */
    private function syncRecord(
        Server $server,
        CloudflareDnsRecord $record,
        ?EggDnsProfile $profile,
        string $apiToken
    ): void {
        $zone = $record->zone;
        if (!$zone) {
            return;
        }

        if ($record->type === EggDnsProfile::TYPE_A) {
            $newContent = $this->dnsService->resolveAllocationIp($server);
            if ($record->content === $newContent) {
                return;
            }

            $remote = $this->apiService->updateDnsRecord(
                $apiToken,
                $zone->zone_id,
                $record->cloudflare_record_id,
                $record->type,
                $record->name,
                $newContent,
                $record->ttl,
                false
            );

            $record->update([
                'content' => $newContent,
                'name' => $remote['name'] ?? $record->name,
            ]);

            return;
        }

        if ($record->type === EggDnsProfile::TYPE_SRV && $profile) {
            $this->syncCompanionARecord($server, $record, $apiToken);

            $srv = $this->dnsService->buildSrvPayload($server, $profile, $record->subdomain, $zone);
            if ($record->content === $srv['content']) {
                return;
            }

            $remote = $this->apiService->updateDnsRecord(
                $apiToken,
                $zone->zone_id,
                $record->cloudflare_record_id,
                EggDnsProfile::TYPE_SRV,
                $srv['name'],
                $srv['content'],
                $record->ttl,
                false,
                $srv['data']
            );

            $record->update([
                'name' => $remote['name'] ?? $srv['full_name'],
                'content' => $srv['content'],
                'srv_service' => $srv['data']['service'],
                'srv_protocol' => $srv['data']['proto'],
                'srv_port' => $srv['data']['port'],
                'srv_priority' => $srv['data']['priority'],
                'srv_weight' => $srv['data']['weight'],
            ]);
        }
    }

    /**
     * @throws DisplayException
     */
    private function syncCompanionARecord(Server $server, CloudflareDnsRecord $srvRecord, string $apiToken): void
    {
        $zone = $srvRecord->zone;
        if (!$zone) {
            return;
        }

        $companion = CloudflareDnsRecord::query()
            ->where('server_id', $server->id)
            ->where('zone_id', $zone->id)
            ->where('subdomain', $srvRecord->subdomain)
            ->where('type', EggDnsProfile::TYPE_A)
            ->where('is_companion', true)
            ->first();

        if (!$companion) {
            return;
        }

        $newContent = $this->dnsService->resolveAllocationIp($server);
        if ($companion->content === $newContent) {
            return;
        }

        $remote = $this->apiService->updateDnsRecord(
            $apiToken,
            $zone->zone_id,
            $companion->cloudflare_record_id,
            EggDnsProfile::TYPE_A,
            $companion->name,
            $newContent,
            $companion->ttl,
            false
        );

        $companion->update([
            'content' => $newContent,
            'name' => $remote['name'] ?? $companion->name,
        ]);
    }
}
