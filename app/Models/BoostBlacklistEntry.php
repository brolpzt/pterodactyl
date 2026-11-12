<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $type ip|steam_id|name
 * @property string $value IP or CIDR, SteamID, or player name
 * @property string|null $reason
 * @property int|null $created_by
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property User|null $creator
 */
class BoostBlacklistEntry extends Model
{
    public const TYPE_IP = 'ip';
    public const TYPE_STEAM_ID = 'steam_id';
    public const TYPE_NAME = 'name';

    public const TYPES = [self::TYPE_IP => 'IP', self::TYPE_STEAM_ID => 'SteamID', self::TYPE_NAME => 'Nome'];

    protected $table = 'boost_blacklist';

    protected $guarded = ['id'];

    protected $casts = [
        'created_by' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
