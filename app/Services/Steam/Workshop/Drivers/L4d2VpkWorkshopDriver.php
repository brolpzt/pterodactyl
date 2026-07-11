<?php

namespace Pterodactyl\Services\Steam\Workshop\Drivers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Steam\Workshop\WorkshopSyncDriver;

class L4d2VpkWorkshopDriver implements WorkshopSyncDriver
{
    public function id(): string
    {
        return Egg::WORKSHOP_SYNC_L4D2_VPK;
    }

    public function label(): string
    {
        return 'Left 4 Dead 2 (VPK em addons/workshop)';
    }

    public function description(): string
    {
        return 'Descarrega mods da Workshop e coloca ficheiros .vpk em left4dead2/addons/workshop/.';
    }

    public function sync(Server $server, int $workshopAppId): void
    {
        throw new DisplayException(
            'O driver L4D2 (VPK) ainda não está implementado. Use "Somente browse" e faça deploy manual dos .vpk por enquanto.'
        );
    }
}
