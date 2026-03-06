<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\TicketMessage;

class TicketMessageTransformer extends BaseClientTransformer
{
    /**
     * @return string
     */
    public function getResourceName(): string
    {
        return 'ticket_message';
    }

    /**
     * @param \Pterodactyl\Models\TicketMessage $message
     * @return array
     */
    public function transform(TicketMessage $message): array
    {
        return [
            'id' => $message->id,
            'ticket_id' => $message->ticket_id,
            'user_id' => $message->user_id,
            'user_name' => $message->user->name_first . ' ' . $message->user->name_last,
            'user_email' => $message->user->email,
            'is_staff' => (bool)$message->user->root_admin,
            'message' => $message->message,
            'created_at' => $message->created_at->toIso8601String(),
            'updated_at' => $message->updated_at->toIso8601String(),
        ];
    }
}
