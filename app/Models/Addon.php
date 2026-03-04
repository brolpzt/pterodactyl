<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $uuid
 * @property int $egg_id
 * @property int|null $category_id
 * @property string $name
 * @property string|null $description
 * @property string $script
 * @property string $container_image
 * @property bool $reinstall_server
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Egg $egg
 * @property \Pterodactyl\Models\AddonCategory|null $category
 * @property \Pterodactyl\Models\Server[]|\Illuminate\Database\Eloquent\Collection $servers
 * @property array|null $pivot
 */
class Addon extends Model
{
    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'addon';

    /**
     * The table associated with the model.
     */
    protected $table = 'addons';

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'is_active' => 'bool',
        'reinstall_server' => 'bool',
        'egg_id' => 'int',
        'category_id' => 'int',
    ];

    /**
     * Validation rules for the model.
     */
    public static array $validationRules = [
        'egg_id' => 'required|numeric|exists:eggs,id',
        'category_id' => 'nullable|numeric|exists:addon_categories,id',
        'name' => 'required|string|max:191',
        'description' => 'nullable|string',
        'script' => 'required|string',
        'container_image' => 'required|string|max:191',
        'reinstall_server' => 'sometimes|boolean',
        'is_active' => 'sometimes|boolean',
    ];

    /**
     * Gets the egg that this addon belongs to.
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * Gets the category that this addon belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AddonCategory::class, 'category_id');
    }

    /**
     * Gets the servers that have this addon installed.
     */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'addon_server')->withPivot('installed_at');
    }
}
