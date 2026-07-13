<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers;

use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class SftpPasswordRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return Permission::ACTION_FILE_SFTP;
    }
}
