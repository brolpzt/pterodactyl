<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $api_token
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\CloudflareZone[] $zones
 */
class CloudflareAccount extends Model
{
    public const RESOURCE_NAME = 'cloudflare_account';

    protected $table = 'cloudflare_accounts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'api_token' => 'nullable|string',
    ];

    public function zones(): HasMany
    {
        return $this->hasMany(CloudflareZone::class);
    }

    public function isConfigured(): bool
    {
        return !empty($this->api_token);
    }
}
