<?php

namespace Pterodactyl\Services\Steam;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerWorkshopItem;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class GmodWorkshopSyncService
{
    private const WORKSHOP_LUA_PATH = 'garrysmod/lua/autorun/server/workshop.lua';

    public function __construct(
        private DaemonFileRepository $fileRepository,
        private SteamWorkshopService $steamWorkshopService,
    ) {
    }

    /**
     * Regenerate workshop.lua from the server's installed workshop items.
     */
    public function sync(Server $server, int $appId): void
    {
        $items = ServerWorkshopItem::query()
            ->where('server_id', $server->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $resolvedIds = [];
        foreach ($items as $item) {
            try {
                $ids = $this->steamWorkshopService->resolveItemIds($appId, (int) $item->published_file_id);
            } catch (DisplayException) {
                $ids = [(int) $item->published_file_id];
            }

            foreach ($ids as $id) {
                $resolvedIds[$id] = true;
            }
        }

        $content = $this->buildWorkshopLua(array_keys($resolvedIds));

        try {
            $this->fileRepository->setServer($server)->putContent(self::WORKSHOP_LUA_PATH, $content);
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
