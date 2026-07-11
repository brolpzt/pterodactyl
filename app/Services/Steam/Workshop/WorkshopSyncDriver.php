<?php

namespace Pterodactyl\Services\Steam\Workshop;

use Pterodactyl\Models\Server;

interface WorkshopSyncDriver
{
    public function id(): string;

    public function label(): string;

    public function description(): string;

    /**
     * Apply installed workshop items to the game server files/configuration.
     */
    public function sync(Server $server, int $workshopAppId): void;
}
