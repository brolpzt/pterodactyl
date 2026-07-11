<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $cloudflare_account_id
 * @property string $zone_id
 * @property string $domain
 * @property bool $is_active
 * @property bool $allow_user_create
 * @property bool $default_proxied
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\CloudflareAccount $account
 */
class CloudflareZone extends Model
{
    public const RESOURCE_NAME = 'cloudflare_zone';

    protected $table = 'cloudflare_zones';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'cloudflare_account_id' => 'integer',
        'is_active' => 'boolean',
        'allow_user_create' => 'boolean',
        'default_proxied' => 'boolean',
    ];

    public static array $validationRules = [
        'cloudflare_account_id' => 'required|integer|exists:cloudflare_accounts,id',
        'zone_id' => 'required|string|max:64',
        'domain' => 'required|string|max:191',
        'is_active' => 'boolean',
        'allow_user_create' => 'boolean',
        'default_proxied' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CloudflareAccount::class, 'cloudflare_account_id');
    }

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(CloudflareDnsRecord::class, 'zone_id');
    }
}
