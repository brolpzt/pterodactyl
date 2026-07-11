<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerWorkshopItem extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'server_workshop_items';

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'server_id',
        'published_file_id',
        'title',
        'preview_url',
        'sort_order',
    ];

    /**
     * Fields to cast to native types.
     */
    protected $casts = [
        'server_id' => 'integer',
        'published_file_id' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Returns the server this workshop item belongs to.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
