<?php

namespace Pterodactyl\Services\Steam\Workshop;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Steam\EggWorkshopFeatureService;
use Pterodactyl\Services\Steam\Workshop\Drivers\ArkIniWorkshopDriver;
use Pterodactyl\Services\Steam\Workshop\Drivers\BrowseOnlyWorkshopDriver;
use Pterodactyl\Services\Steam\Workshop\Drivers\GmodLuaWorkshopDriver;
use Pterodactyl\Services\Steam\Workshop\Drivers\L4d2VpkWorkshopDriver;
use Pterodactyl\Services\Steam\Workshop\Drivers\SourceCollectionWorkshopDriver;

class WorkshopSyncManager
{
    /** @var array<string, WorkshopSyncDriver> */
    private array $drivers;

    public function __construct(
        GmodLuaWorkshopDriver $gmodLua,
        BrowseOnlyWorkshopDriver $browseOnly,
        L4d2VpkWorkshopDriver $l4d2Vpk,
        ArkIniWorkshopDriver $arkIni,
        SourceCollectionWorkshopDriver $sourceCollection,
    ) {
        foreach ([$gmodLua, $browseOnly, $l4d2Vpk, $arkIni, $sourceCollection] as $driver) {
            $this->drivers[$driver->id()] = $driver;
        }
    }

    /**
     * @return array<int, array{id: string, label: string, description: string}>
     */
    public function definitions(): array
    {
        return array_values(array_map(
            fn (WorkshopSyncDriver $driver) => [
                'id' => $driver->id(),
                'label' => $driver->label(),
                'description' => $driver->description(),
            ],
            $this->drivers
        ));
    }

    public function sync(Server $server, int $workshopAppId): void
    {
        $this->driverForServer($server)->sync($server, $workshopAppId);
    }

    public function driverForServer(Server $server): WorkshopSyncDriver
    {
        $server->loadMissing('egg.configFrom');
        $driverId = app(EggWorkshopFeatureService::class)->resolveWorkshopSyncDriver($server->egg)
            ?? $this->guessDriverId($server->egg);

        if (!isset($this->drivers[$driverId])) {
            throw new DisplayException("Driver Workshop desconhecido: {$driverId}");
        }

        return $this->drivers[$driverId];
    }

    private function guessDriverId(Egg $egg): string
    {
        $name = strtolower($egg->name ?? '');

        if (str_contains($name, 'garry') || str_contains($name, 'gmod')) {
            return Egg::WORKSHOP_SYNC_GMOD_LUA;
        }

        if (str_contains($name, 'left 4 dead') || str_contains($name, 'l4d')) {
            return Egg::WORKSHOP_SYNC_L4D2_VPK;
        }

        if (str_contains($name, 'ark')) {
            return Egg::WORKSHOP_SYNC_ARK_INI;
        }

        return Egg::WORKSHOP_SYNC_BROWSE_ONLY;
    }
}
