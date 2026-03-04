<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property string $ip
 * @property string|null $reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Server $server
 */
class FirewallRule extends Model
{
    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'firewall_rule';

    /**
     * The table associated with the model.
     */
    protected $table = 'firewall_rules';

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'server_id' => 'int',
    ];

    /**
     * Validation rules for the model.
     */
    public static array $validationRules = [
        'server_id' => 'required|numeric|exists:servers,id',
        'ip'        => 'required|string|ip|max:45',
        'reason'    => 'nullable|string|max:255',
    ];

    /**
     * Gets the server that this rule belongs to.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
