<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\Server;

class TicketTransformer extends BaseClientTransformer
{
    /**
     * @return string
     */
    public function getResourceName(): string
    {
        return 'ticket';
    }

    /**
     * @param \Pterodactyl\Models\Ticket $ticket
     * @return array
     */
    public function transform(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'department' => $ticket->department,
            'status' => $ticket->status,
            'server_id' => $ticket->server_id,
            'server_name' => collect([$ticket->server])->filter()->first()?->name,
            'created_at' => $ticket->created_at->toIso8601String(),
            'updated_at' => $ticket->updated_at->toIso8601String(),
        ];
    }
}
