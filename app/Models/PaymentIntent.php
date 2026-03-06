<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $gateway
 * @property string|null $external_id
 * @property float $amount
 * @property string $status
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property \Pterodactyl\Models\User $user
 */
class PaymentIntent extends Model
{
    protected $table = 'payment_intents';

    protected $fillable = [
        'user_id',
        'gateway',
        'external_id',
        'amount',
        'status',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
