<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;

class WebRcon
{
    public static function isEnabled(Server $server): bool
    {
        $egg = $server->egg;
        if (!$egg) {
            return false;
        }

        $features = $egg->inherit_features ?? [];

        return in_array(Egg::FEATURE_WEBRCON, $features, true);
    }
}
