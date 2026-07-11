<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Workshop;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UninstallWorkshopItemRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'workshop.manage';
    }

    public function rules(): array
    {
        return [];
    }
}
