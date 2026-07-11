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

    public static function isCs16(Server $server): bool
    {
        $server->loadMissing('egg');

        $gamedig = strtolower(trim((string) ($server->egg->gamedig ?? '')));
        if (in_array($gamedig, ['cs16', 'counterstrike16', 'goldsource', 'cstrike'], true)) {
            return true;
        }

        $eggName = strtolower((string) ($server->egg->name ?? ''));

        return (bool) preg_match('/counter-?strike\s*1\.?6|\bcs\s*1\.?6\b|\bcs16\b|rehlds|\bhlds\b/i', $eggName);
    }
}
