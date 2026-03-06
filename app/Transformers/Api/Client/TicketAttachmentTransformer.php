<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\TicketAttachment;

class TicketAttachmentTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return 'ticket_attachment';
    }

    /**
     * @param \Pterodactyl\Models\TicketAttachment $attachment
     * @return array
     */
    public function transform(TicketAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'filename' => $attachment->filename,
            'size' => $attachment->size,
            'url' => route('api.client.account.tickets.attachment', [$attachment->hash]),
        ];
    }
}
