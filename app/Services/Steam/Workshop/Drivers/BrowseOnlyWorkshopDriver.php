<?php

namespace Pterodactyl\Services\Steam\Workshop\Drivers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Steam\Workshop\WorkshopSyncDriver;

class BrowseOnlyWorkshopDriver implements WorkshopSyncDriver
{
    public function id(): string
    {
        return Egg::WORKSHOP_SYNC_BROWSE_ONLY;
    }

    public function label(): string
    {
        return 'Somente browse (sem sync automático)';
    }

    public function description(): string
    {
        return 'Lista mods na Steam Workshop e guarda a seleção no painel, sem alterar ficheiros do servidor.';
    }

    public function sync(Server $server, int $workshopAppId): void
    {
        // Persistência fica em server_workshop_items; deploy manual pelo operador.
    }
}
