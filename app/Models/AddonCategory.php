<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $egg_id
 * @property string $name
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Egg $egg
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Addon[] $addons
 */
class AddonCategory extends Model
{
    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'addon_category';

    /**
     * The table associated with the model.
     */
    protected $table = 'addon_categories';

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'egg_id' => 'int',
    ];

    /**
     * Validation rules for the model.
     */
    public static array $validationRules = [
        'egg_id' => 'required|numeric|exists:eggs,id',
        'name' => 'required|string|max:191',
        'description' => 'nullable|string',
    ];

    /**
     * Gets the egg that this category belongs to.
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * Gets the addons that belong to this category.
     */
    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class, 'category_id');
    }
}
