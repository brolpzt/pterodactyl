<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\AntiDdos;

use Pterodactyl\Models\Permission;
use Pterodactyl\Contracts\Http\ClientPermissionsRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class UpdateDdosProfileRequest extends ClientApiRequest implements ClientPermissionsRequest
{
    public function permission(): string
    {
        return Permission::ACTION_ANTIDDOS_PROFILE;
    }

    public function rules(): array
    {
        return [
            'profile' => 'required|integer|min:1',
        ];
    }
}
