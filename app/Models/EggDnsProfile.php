<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $egg_id
 * @property bool $enabled
 * @property array|null $allowed_types
 * @property string $default_type
 * @property int $max_records_per_server
 * @property string|null $srv_service
 * @property string|null $srv_protocol
 * @property int $srv_priority
 * @property int $srv_weight
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Egg $egg
 */
class EggDnsProfile extends Model
{
    public const TYPE_A = 'A';

    public const TYPE_CNAME = 'CNAME';

    public const TYPE_SRV = 'SRV';

    public const RESOURCE_NAME = 'egg_dns_profile';

    protected $table = 'egg_dns_profiles';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'egg_id' => 'integer',
        'enabled' => 'boolean',
        'allowed_types' => 'array',
        'max_records_per_server' => 'integer',
        'srv_priority' => 'integer',
        'srv_weight' => 'integer',
    ];

    public static array $validationRules = [
        'egg_id' => 'required|integer|exists:eggs,id',
        'enabled' => 'boolean',
        'allowed_types' => 'nullable|array',
        'allowed_types.*' => 'string|in:A,CNAME,SRV',
        'default_type' => 'required|string|in:A,CNAME,SRV',
        'max_records_per_server' => 'required|integer|min:1|max:50',
        'srv_service' => 'nullable|string|max:32',
        'srv_protocol' => 'nullable|string|max:16',
        'srv_priority' => 'integer|min:0|max:65535',
        'srv_weight' => 'integer|min:0|max:65535',
    ];

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    public function allowsType(string $type): bool
    {
        $types = $this->allowed_types ?? [self::TYPE_A];

        return in_array($type, $types, true);
    }

    public function srvService(): string
    {
        $service = $this->srv_service ?: '_minecraft';

        return str_starts_with($service, '_') ? $service : "_{$service}";
    }

    public function srvProtocol(): string
    {
        $protocol = $this->srv_protocol ?: '_tcp';

        return str_starts_with($protocol, '_') ? $protocol : "_{$protocol}";
    }
}
