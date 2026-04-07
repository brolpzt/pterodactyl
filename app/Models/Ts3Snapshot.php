<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property string $uuid
 * @property string $name
 * @property string $snapshot
 * @property int|null $created_by
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 * @property Server $server
 */
class Ts3Snapshot extends Model
{
    public const RESOURCE_NAME = 'ts3_snapshot';

    protected $table = 'ts3_snapshots';

    protected bool $immutableDates = true;

    protected $casts = [
        'id' => 'int',
        'server_id' => 'int',
        'created_by' => 'int',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public static array $validationRules = [
        'server_id' => 'bail|required|numeric|exists:servers,id',
        'uuid' => 'required|uuid',
        'name' => 'required|string|max:191',
        'snapshot' => 'required|string',
        'created_by' => 'nullable|numeric|exists:users,id',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
