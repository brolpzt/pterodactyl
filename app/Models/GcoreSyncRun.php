<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $kind ports|ip_list
 * @property int|null $node_id
 * @property string|null $ip
 * @property int|null $ip_list_id
 * @property string|null $mode
 * @property bool $force
 * @property string $label
 * @property list<string> $reasons
 * @property string $status queued|running|ok|error
 * @property int $attempts
 * @property string|null $summary
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GcoreSyncRun extends Model
{
    public const KIND_PORTS = 'ports';
    public const KIND_IP_LIST = 'ip_list';

    protected $table = 'gcore_sync_runs';

    protected $guarded = ['id'];

    protected $casts = [
        'node_id' => 'integer',
        'ip_list_id' => 'integer',
        'force' => 'boolean',
        'reasons' => 'array',
        'attempts' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }
}
