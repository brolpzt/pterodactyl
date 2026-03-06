<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property \Pterodactyl\Models\Ticket $ticket
 * @property \Pterodactyl\Models\User $user
 * @property \Pterodactyl\Models\TicketAttachment[] $attachments
 */
class TicketMessage extends Model
{
    /**
     * @var string
     */
    protected $table = 'ticket_messages';

    /**
     * @var array
     */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'ticket_message_id');
    }
}
