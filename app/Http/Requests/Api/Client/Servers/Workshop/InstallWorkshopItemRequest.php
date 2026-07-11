<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Workshop;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class InstallWorkshopItemRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'workshop.manage';
    }

    public function rules(): array
    {
        return [
            'published_file_id' => 'required|integer|min:1',
        ];
    }
}
