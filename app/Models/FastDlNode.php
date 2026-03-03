<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int $location_id
 * @property string $fqdn
 * @property int $port
 * @property string $remote_path
 * @property string $username
 * @property string|null $password
 * @property string|null $private_key
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Location $location
 */
class FastDlNode extends Model
{
    use HasFactory;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'fast_dl_node';

    /**
     * The table associated with the model.
     */
    protected $table = 'fast_dl_nodes';

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'location_id' => 'integer',
        'port' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Rules ensuring that the raw data stored in the database meets expectations.
     */
    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'location_id' => 'required|exists:locations,id',
        'fqdn' => 'required|string|max:191',
        'port' => 'required|integer|between:1,65535',
        'remote_path' => 'required|string|max:191',
        'username' => 'required|string|max:191',
        'password' => 'nullable|string',
        'private_key' => 'nullable|string',
        'is_active' => 'boolean',
    ];

    /**
     * Gets the location associated with a FastDL node.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
