<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ticket_message_id
 * @property string $filename
 * @property string $hash
 * @property string $mime_type
 * @property int $size
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property \Pterodactyl\Models\TicketMessage $message
 */
class TicketAttachment extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_attachments';

    /**
     * Fields that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ticket_message_id', 'filename', 'hash', 'mime_type', 'size',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }
}
