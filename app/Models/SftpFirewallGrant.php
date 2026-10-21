<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $node_id
 * @property int|null $server_id
 * @property string $client_ip
 * @property string $provider
 * @property string|null $external_rule_id
 * @property string $status
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $revoked_at
 * @property string|null $last_error
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property User $user
 * @property Node $node
 * @property Server|null $server
 */
class SftpFirewallGrant extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_FAILED = 'failed';

    protected $table = 'sftp_firewall_grants';

    protected $fillable = [
        'user_id',
        'node_id',
        'server_id',
        'client_ip',
        'provider',
        'external_rule_id',
        'status',
        'expires_at',
        'revoked_at',
        'last_error',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'node_id' => 'integer',
        'server_id' => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static array $validationRules = [
        'user_id' => 'required|integer|exists:users,id',
        'node_id' => 'required|integer|exists:nodes,id',
        'server_id' => 'sometimes|nullable|integer|exists:servers,id',
        'client_ip' => 'required|ip',
        'provider' => 'required|string|max:32',
        'external_rule_id' => 'sometimes|nullable|string|max:191',
        'status' => 'required|string|in:active,revoked,failed',
        'expires_at' => 'required|date',
        'revoked_at' => 'sometimes|nullable|date',
        'last_error' => 'sometimes|nullable|string',
    ];

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
