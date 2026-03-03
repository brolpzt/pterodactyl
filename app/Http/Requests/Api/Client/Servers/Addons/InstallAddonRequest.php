<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Addons;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class InstallAddonRequest extends ClientApiRequest
{
    /**
     * Rules to apply to the request.
     */
    public function rules(): array
    {
        return [];
    }
}
