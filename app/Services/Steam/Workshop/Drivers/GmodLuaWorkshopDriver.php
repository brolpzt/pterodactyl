<?php

namespace Pterodactyl\Services\Steam\Workshop\Drivers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerWorkshopItem;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Steam\SteamWorkshopService;
use Pterodactyl\Services\Steam\Workshop\WorkshopSyncDriver;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class GmodLuaWorkshopDriver implements WorkshopSyncDriver
{
    private const WORKSHOP_LUA_PATH = 'garrysmod/lua/autorun/server/workshop.lua';

    public function __construct(
        private DaemonFileRepository $fileRepository,
        private SteamWorkshopService $steamWorkshopService,
    ) {
    }

    public function id(): string
    {
        return Egg::WORKSHOP_SYNC_GMOD_LUA;
    }

    public function label(): string
    {
        return 'Garry\'s Mod (workshop.lua)';
    }

    public function description(): string
    {
        return 'Escreve resource.AddWorkshop() em garrysmod/lua/autorun/server/workshop.lua.';
    }

    public function sync(Server $server, int $workshopAppId): void
    {
        $items = ServerWorkshopItem::query()
            ->where('server_id', $server->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $resolvedIds = [];
        foreach ($items as $item) {
            try {
                $ids = $this->steamWorkshopService->resolveItemIds($workshopAppId, (int) $item->published_file_id);
            } catch (DisplayException) {
                $ids = [(int) $item->published_file_id];
            }

            foreach ($ids as $id) {
                $resolvedIds[$id] = true;
            }
        }

        try {
            $this->fileRepository->setServer($server)->putContent(
                self::WORKSHOP_LUA_PATH,
                $this->buildWorkshopLua(array_keys($resolvedIds))
            );
        } catch (DaemonConnectionException $exception) {
            throw new DisplayException(
                'Não foi possível atualizar workshop.lua no servidor. Verifique se o Garry\'s Mod está instalado.',
                $exception
            );
        }
    }

    /**
     * @param  int[]  $fileIds
     */
    private function buildWorkshopLua(array $fileIds): string
    {
        $lines = [
            '-- Gerenciado pelo painel Pterodactyl — não edite manualmente.',
            '-- Docs: https://wiki.garrysmod.com/page/resource/AddWorkshop',
            '',
        ];

        sort($fileIds);
        foreach ($fileIds as $fileId) {
            $lines[] = sprintf('resource.AddWorkshop( "%d" )', $fileId);
        }

        if ($fileIds === []) {
            $lines[] = '-- Nenhum mod Workshop configurado.';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
