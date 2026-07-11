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
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Egg $egg
 */
class EggDnsProfile extends Model
{
    public const TYPE_A = 'A';

    public const TYPE_CNAME = 'CNAME';

    public const RESOURCE_NAME = 'egg_dns_profile';

    protected $table = 'egg_dns_profiles';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'egg_id' => 'integer',
        'enabled' => 'boolean',
        'allowed_types' => 'array',
        'max_records_per_server' => 'integer',
    ];

    public static array $validationRules = [
        'egg_id' => 'required|integer|exists:eggs,id',
        'enabled' => 'boolean',
        'allowed_types' => 'nullable|array',
        'allowed_types.*' => 'string|in:A,CNAME',
        'default_type' => 'required|string|in:A,CNAME',
        'max_records_per_server' => 'required|integer|min:1|max:50',
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
}
