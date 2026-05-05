<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $server_id
 * @property int|null $department_id
 * @property string $subject
 * @property string $department
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property \Pterodactyl\Models\User $user
 * @property \Pterodactyl\Models\Server|null $server
 * @property \Pterodactyl\Models\TicketDepartment|null $ticketDepartment
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\TicketMessage[] $messages
 */
class Ticket extends Model
{
    /**
     * @var string
     */
    protected $table = 'tickets';

    /**
     * @var array
     */
    protected $fillable = [
        'user_id',
        'server_id',
        'department_id',
        'subject',
        'department',
        'status',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ticketDepartment(): BelongsTo
    {
        return $this->belongsTo(TicketDepartment::class, 'department_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at', 'desc');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(TicketMessage::class)->latestOfMany('created_at');
    }
}
