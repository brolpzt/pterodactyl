<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Workshop;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class BrowseWorkshopRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'workshop.read';
    }

    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:200',
            'sort' => 'nullable|string|in:trending,popular,recent',
            'cursor' => 'nullable|string|max:500',
            'per_page' => 'nullable|integer|min:1|max:50',
        ];
    }
}
