<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\Server;

class ServerType
{
    public const TS3_EGG_ID = 12;

    public static function isTs3(Server $server): bool
    {
        return (int) $server->egg_id === self::TS3_EGG_ID;
    }
}
