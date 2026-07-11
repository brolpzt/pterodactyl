<?php

namespace Pterodactyl\Services\Steam\Workshop\Drivers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Steam\Workshop\WorkshopSyncDriver;

class ArkIniWorkshopDriver implements WorkshopSyncDriver
{
    public function id(): string
    {
        return Egg::WORKSHOP_SYNC_ARK_INI;
    }

    public function label(): string
    {
        return 'ARK (GameUserSettings.ini)';
    }

    public function description(): string
    {
        return 'Atualiza ActiveMods em GameUserSettings.ini e sincroniza conteúdo Workshop.';
    }

    public function sync(Server $server, int $workshopAppId): void
    {
        throw new DisplayException(
            'O driver ARK ainda não está implementado. Use "Somente browse" ou configure mods manualmente por enquanto.'
        );
    }
}
