<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One real (non dry-run) push of a node's XDP port map to Wings.
 *
 * @property int $id
 * @property int $node_id
 * @property string $trigger manual | automatic | reconcile
 * @property string|null $reason
 * @property bool $ok
 * @property int $ports
 * @property int $added
 * @property int $updated
 * @property int $removed
 * @property int $unchanged
 * @property array|null $changes {added: list, updated: list, removed: list}
 * @property string|null $error
 * @property \Carbon\Carbon $created_at
 */
class XdpSyncLog extends Model
{
    use Prunable;

    public const TRIGGER_MANUAL = 'manual';
    public const TRIGGER_AUTOMATIC = 'automatic';
    public const TRIGGER_RECONCILE = 'reconcile';

    public const TRIGGER_LABELS = [
        self::TRIGGER_MANUAL => 'Manual',
        self::TRIGGER_AUTOMATIC => 'Automática',
        self::TRIGGER_RECONCILE => 'Reconciliação',
    ];

    public const RETENTION_DAYS = 30;

    public const UPDATED_AT = null;

    protected $table = 'xdp_sync_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'ok' => 'boolean',
        'ports' => 'integer',
        'added' => 'integer',
        'updated' => 'integer',
        'removed' => 'integer',
        'unchanged' => 'integer',
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function changedPorts(): bool
    {
        return $this->added + $this->updated + $this->removed > 0;
    }

    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
