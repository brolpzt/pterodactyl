<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Workshop;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ListInstalledWorkshopRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'workshop.read';
    }

    public function rules(): array
    {
        return [];
    }
}
