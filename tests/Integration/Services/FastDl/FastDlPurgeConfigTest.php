<?php

namespace Pterodactyl\Tests\Integration\Services\FastDl;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\FastDlNode;
use Pterodactyl\Models\CloudflareZone;
use Pterodactyl\Models\CloudflareAccount;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Services\Servers\FastDlSyncService;
use Pterodactyl\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class FastDlPurgeConfigTest extends IntegrationTestCase
{
    use DatabaseTransactions;

    private function node(array $attributes = []): FastDlNode
    {
        return FastDlNode::query()->forceCreate(array_merge([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => 'fastdl',
            'location_id' => Location::factory()->create()->id,
            'storage_type' => FastDlNode::STORAGE_S3,
            'fqdn' => 'fastdl.example.com',
            'port' => 443,
            'remote_path' => '/',
            'bucket' => 'bucket',
            'endpoint' => 'https://x.r2.cloudflarestorage.com',
            'is_active' => true,
        ], $attributes));
    }

    public function testNoZoneMeansNoPurge(): void
    {
        $service = $this->app->make(FastDlSyncService::class);

        $this->assertNull($service->purgeConfigFor($this->node()));
    }

    public function testZoneGivesCloudflarePurgeConfig(): void
    {
        $encrypter = $this->app->make(Encrypter::class);
        $account = CloudflareAccount::query()->create(['name' => 'CF', 'api_token' => $encrypter->encrypt('cf-token')]);
        $zone = CloudflareZone::query()->forceCreate([
            'cloudflare_account_id' => $account->id, 'zone_id' => 'zone123', 'domain' => 'example.com',
            'label' => 'example.com', 'public_domain' => 'example.com', 'is_active' => true, 'allow_user_create' => false, 'default_proxied' => false,
        ]);

        $service = $this->app->make(FastDlSyncService::class);

        $this->assertSame([
            'provider' => 'cloudflare',
            'zone_id' => 'zone123',
            'api_token' => 'cf-token',
            'base_url' => 'http://fastdl.example.com',
        ], $service->purgeConfigFor($this->node(['cloudflare_zone_id' => $zone->id, 'public_url' => 'http://fastdl.example.com/'])));

        // Without a public URL the base falls back to http://<fqdn>, like the download URL.
        $this->assertSame('http://fastdl.example.com', $service->purgeConfigFor($this->node(['cloudflare_zone_id' => $zone->id]))['base_url']);

        // The node's own purge-only token wins over the account token.
        $this->assertSame('purge-token', $service->purgeConfigFor($this->node([
            'cloudflare_zone_id' => $zone->id,
            'cloudflare_purge_token' => $encrypter->encrypt('purge-token'),
        ]))['api_token']);

        // An account without a token cannot purge.
        $account->forceFill(['api_token' => null])->save();
        $this->assertNull($service->purgeConfigFor($this->node(['cloudflare_zone_id' => $zone->id])));
    }
}
