<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property int $zone_id
 * @property string $cloudflare_record_id
 * @property string $type
 * @property string $subdomain
 * @property string $name
 * @property string $content
 * @property int $ttl
 * @property bool $proxied
 * @property string|null $srv_service
 * @property string|null $srv_protocol
 * @property int|null $srv_port
 * @property int|null $srv_priority
 * @property int|null $srv_weight
 * @property int|null $created_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Server $server
 * @property \Pterodactyl\Models\CloudflareZone $zone
 */
class CloudflareDnsRecord extends Model
{
    public const RESOURCE_NAME = 'cloudflare_dns_record';

    protected $table = 'cloudflare_dns_records';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'server_id' => 'integer',
        'zone_id' => 'integer',
        'ttl' => 'integer',
        'proxied' => 'boolean',
        'srv_port' => 'integer',
        'srv_priority' => 'integer',
        'srv_weight' => 'integer',
        'created_by' => 'integer',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'zone_id' => 'required|integer|exists:cloudflare_zones,id',
        'cloudflare_record_id' => 'required|string|max:64',
        'type' => 'required|string|in:A,CNAME,SRV',
        'subdomain' => 'required|string|max:191',
        'name' => 'required|string|max:255',
        'content' => 'required|string|max:512',
        'ttl' => 'integer|min:1',
        'proxied' => 'boolean',
        'srv_service' => 'nullable|string|max:32',
        'srv_protocol' => 'nullable|string|max:16',
        'srv_port' => 'nullable|integer|min:1|max:65535',
        'srv_priority' => 'nullable|integer|min:0|max:65535',
        'srv_weight' => 'nullable|integer|min:0|max:65535',
        'created_by' => 'nullable|integer|exists:users,id',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(CloudflareZone::class, 'zone_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
