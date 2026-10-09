<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $node_id
 * @property string $ip
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property Node $node
 */
class NodeGcoreIp extends Model
{
    protected $table = 'node_gcore_ips';

    protected $fillable = ['node_id', 'ip'];

    protected $casts = [
        'node_id' => 'integer',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
