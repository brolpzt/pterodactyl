<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player the boost system sent to a server.
 *
 * @property int $id
 * @property int|null $server_id
 * @property string $address ip:port as reported by the boost system
 * @property string|null $player_name
 * @property string|null $player_ip
 * @property string|null $steam_id
 * @property string|null $source_ip address that called the endpoint
 * @property \Illuminate\Support\Carbon $created_at
 * @property Server|null $server
 */
class BoostConnection extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'boost_connections';

    protected $guarded = ['id'];

    protected $casts = [
        'server_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
