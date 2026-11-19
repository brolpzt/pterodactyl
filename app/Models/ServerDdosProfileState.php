<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property int|null $egg_ddos_profile_id option applied through the profile picker
 * @property \Carbon\Carbon|null $client_changed_at last change made by the client (cooldown)
 * @property bool $client_locked picker hidden for the client (default until the admin shows it)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property EggDdosProfile|null $profile
 */
class ServerDdosProfileState extends Model
{
    protected $table = 'server_ddos_profile_states';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'server_id' => 'integer',
        'egg_ddos_profile_id' => 'integer',
        'client_changed_at' => 'datetime',
        'client_locked' => 'boolean',
    ];

    // The picker is hidden until the admin shows it for the server.
    protected $attributes = [
        'client_locked' => true,
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EggDdosProfile::class, 'egg_ddos_profile_id');
    }
}
