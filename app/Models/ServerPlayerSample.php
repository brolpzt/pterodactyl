<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property int $players
 * @property int $max_players
 * @property bool $online
 * @property \Illuminate\Support\Carbon $sampled_at
 * @property Server $server
 */
class ServerPlayerSample extends Model
{
    public $timestamps = false;

    protected $table = 'server_player_samples';

    protected $fillable = [
        'server_id',
        'players',
        'max_players',
        'online',
        'sampled_at',
    ];

    protected $casts = [
        'server_id' => 'integer',
        'players' => 'integer',
        'max_players' => 'integer',
        'online' => 'boolean',
        'sampled_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Pterodactyl\Models\Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
