<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property int $egg_forced_command_id
 * @property bool|null $enabled
 * @property bool|null $hide_output
 * @property int|null $interval_seconds
 * @property \Carbon\Carbon|null $last_forced_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Server $server
 * @property EggForcedCommand $eggForcedCommand
 */
class ServerForcedCommandOverride extends Model
{
    protected $table = 'server_forced_command_overrides';

    protected $fillable = [
        'server_id',
        'egg_forced_command_id',
        'enabled',
        'hide_output',
        'interval_seconds',
        'last_forced_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'egg_forced_command_id' => 'integer',
        // Keep enabled/hide_output uncast so null (inherit from egg) is preserved.
        'interval_seconds' => 'integer',
        'last_forced_at' => 'datetime',
    ];

    public static array $validationRules = [
        'server_id' => 'required|integer|exists:servers,id',
        'egg_forced_command_id' => 'required|integer|exists:egg_forced_commands,id',
        'enabled' => 'sometimes|nullable|boolean',
        'hide_output' => 'sometimes|nullable|boolean',
        'interval_seconds' => 'sometimes|nullable|integer|min:15|max:3600',
        'last_forced_at' => 'sometimes|nullable|date',
    ];

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo<EggForcedCommand, $this>
     */
    public function eggForcedCommand(): BelongsTo
    {
        return $this->belongsTo(EggForcedCommand::class);
    }
}
