<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int $location_id
 * @property string $storage_type
 * @property string $fqdn
 * @property int $port
 * @property string $remote_path
 * @property string|null $bucket
 * @property string|null $endpoint
 * @property string|null $region
 * @property string|null $access_key
 * @property string|null $secret_key
 * @property bool $use_path_style_endpoint
 * @property string|null $public_url
 * @property string|null $sync_patterns
 * @property string|null $username
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

    public const STORAGE_SSH = 'ssh';

    public const STORAGE_S3 = 's3';

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
        'use_path_style_endpoint' => 'boolean',
    ];

    /**
     * Rules ensuring that the raw data stored in the database meets expectations.
     */
    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'location_id' => 'required|exists:locations,id',
        'storage_type' => 'required|in:ssh,s3',
        'fqdn' => 'required|string|max:191',
        'port' => 'required|integer|between:1,65535',
        'remote_path' => 'required|string|max:191',
        'bucket' => 'nullable|string|max:191',
        'endpoint' => 'nullable|string|max:512',
        'region' => 'nullable|string|max:64',
        'access_key' => 'nullable|string',
        'secret_key' => 'nullable|string',
        'use_path_style_endpoint' => 'boolean',
        'public_url' => 'nullable|string|max:512',
        'sync_patterns' => 'nullable|string',
        'username' => 'nullable|string|max:191',
        'password' => 'nullable|string',
        'private_key' => 'nullable|string',
        'is_active' => 'boolean',
    ];

    public function isS3(): bool
    {
        return $this->storage_type === self::STORAGE_S3;
    }

    public function isSsh(): bool
    {
        return $this->storage_type === self::STORAGE_SSH;
    }

    /**
     * Gets the location associated with a FastDL node.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
