<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $gcore_ip_list_id
 * @property string $status
 * @property string $trigger
 * @property int|null $http_status
 * @property int $previous_count
 * @property int $entry_count
 * @property list<string>|null $added
 * @property list<string>|null $removed
 * @property int $ignored_ipv6
 * @property int $ignored_invalid
 * @property list<array<string, mixed>>|null $profiles
 * @property string|null $message
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class GcoreIpListLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gcore_ip_list_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'http_status' => 'integer',
        'previous_count' => 'integer',
        'entry_count' => 'integer',
        'added' => 'array',
        'removed' => 'array',
        'ignored_ipv6' => 'integer',
        'ignored_invalid' => 'integer',
        'profiles' => 'array',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(GcoreIpList::class, 'gcore_ip_list_id');
    }
}
