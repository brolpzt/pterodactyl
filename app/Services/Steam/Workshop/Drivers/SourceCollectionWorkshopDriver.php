<?php

namespace Pterodactyl\Services\Steam\Workshop\Drivers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Steam\Workshop\WorkshopSyncDriver;

class SourceCollectionWorkshopDriver implements WorkshopSyncDriver
{
    public function id(): string
    {
        return Egg::WORKSHOP_SYNC_SOURCE_COLLECTION;
    }

    public function label(): string
    {
        return 'Source Engine (+host_workshop_collection)';
    }

    public function description(): string
    {
        return 'Sincroniza IDs para a variável WORKSHOP_ID / startup (+host_workshop_collection).';
    }

    public function sync(Server $server, int $workshopAppId): void
    {
        throw new DisplayException(
            'O driver Source Collection ainda não está implementado. Configure WORKSHOP_ID manualmente no Startup por enquanto.'
        );
    }
}
