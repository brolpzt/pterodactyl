<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $egg_id
 * @property string $name
 * @property int $memory
 * @property int $disk
 * @property int $cpu
 * @property int $swap
 * @property int $io
 * @property float $hourly_rate
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Egg $egg
 */
class DeployPlan extends Model
{
    protected $table = 'deploy_plans';

    protected $fillable = [
        'egg_id',
        'name',
        'memory',
        'disk',
        'cpu',
        'swap',
        'io',
        'hourly_rate',
    ];

    protected $casts = [
        'egg_id' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'cpu' => 'integer',
        'swap' => 'integer',
        'io' => 'integer',
        'hourly_rate' => 'float',
    ];

    public static array $validationRules = [
        'egg_id' => 'required|integer|exists:eggs,id',
        'name' => 'required|string|max:191',
        'memory' => 'required|integer|min:128',
        'disk' => 'required|integer|min:512',
        'cpu' => 'required|integer|min:0',
        'swap' => 'nullable|integer|min:0',
        'io' => 'nullable|integer|min:10',
        'hourly_rate' => 'required|numeric|min:0',
    ];

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }
}
