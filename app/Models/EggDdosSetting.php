<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $egg_id
 * @property bool $enabled
 * @property int $cooldown_minutes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EggDdosSetting extends Model
{
    public const DEFAULT_COOLDOWN = 30;

    protected $table = 'egg_ddos_settings';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'egg_id' => 'integer',
        'enabled' => 'boolean',
        'cooldown_minutes' => 'integer',
    ];

    protected $attributes = [
        'enabled' => false,
        'cooldown_minutes' => self::DEFAULT_COOLDOWN,
    ];

    public static array $validationRules = [
        'egg_id' => 'required|integer|exists:eggs,id',
        'enabled' => 'boolean',
        'cooldown_minutes' => 'integer|between:0,1440',
    ];

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }
}
