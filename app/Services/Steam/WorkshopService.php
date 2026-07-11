<?php

namespace Pterodactyl\Services\Steam;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerWorkshopItem;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Steam\Workshop\WorkshopSyncManager;

class WorkshopService
{
    /**
     * Maps dedicated server / tool app IDs to the consumer game app ID used by Workshop.
     *
     * @var array<int, int>
     */
    private const WORKSHOP_CONSUMER_APP_IDS = [
        4020 => 4000, // Garry's Mod Dedicated Server -> Garry's Mod
        222860 => 550, // L4D2 Dedicated Server -> Left 4 Dead 2
    ];

    public function __construct(
        private SteamWorkshopService $steamWorkshopService,
        private WorkshopSyncManager $workshopSyncManager,
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, next_cursor: string|null, app_id: int, sync: array<string, string>}
     */
    public function browse(Server $server, string $search, string $sort, ?string $cursor, int $perPage): array
    {
        $this->assertWorkshopEnabled($server);
        $appId = $this->resolveAppId($server);

        $result = $this->steamWorkshopService->browse($appId, $search, $sort, $cursor, $perPage);
        $result['app_id'] = $appId;
        $result['sync'] = $this->syncMetadata($server);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function itemDetails(Server $server, int $publishedFileId): array
    {
        $this->assertWorkshopEnabled($server);
        $appId = $this->resolveAppId($server);

        $items = $this->steamWorkshopService->getDetails($appId, [$publishedFileId]);
        if ($items === []) {
            throw new DisplayException('Item Workshop não encontrado.');
        }

        return $items[0];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, sync: array<string, string>}
     */
    public function installed(Server $server): array
    {
        $this->assertWorkshopEnabled($server);

        $items = ServerWorkshopItem::query()
            ->where('server_id', $server->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ServerWorkshopItem $item) => [
                'id' => $item->id,
                'published_file_id' => (string) $item->published_file_id,
                'title' => $item->title,
                'preview_url' => $item->preview_url,
                'sort_order' => $item->sort_order,
                'created_at' => $item->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'items' => $items,
            'sync' => $this->syncMetadata($server),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function install(Server $server, int $publishedFileId): array
    {
        $this->assertWorkshopEnabled($server);
        $appId = $this->resolveAppId($server);

        $details = $this->steamWorkshopService->getDetails($appId, [$publishedFileId]);
        if ($details === []) {
            throw new DisplayException('Item Workshop não encontrado ou indisponível.');
        }

        $detail = $details[0];
        $existing = ServerWorkshopItem::query()
            ->where('server_id', $server->id)
            ->where('published_file_id', $publishedFileId)
            ->first();

        if ($existing) {
            throw new DisplayException('Este item Workshop já está instalado neste servidor.');
        }

        $maxSort = (int) ServerWorkshopItem::query()->where('server_id', $server->id)->max('sort_order');

        $item = ServerWorkshopItem::query()->create([
            'server_id' => $server->id,
            'published_file_id' => $publishedFileId,
            'title' => $detail['title'] ?? null,
            'preview_url' => $detail['preview_url'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        $this->syncServerFiles($server, $appId);

        return [
            'id' => $item->id,
            'published_file_id' => (string) $item->published_file_id,
            'title' => $item->title,
            'preview_url' => $item->preview_url,
            'requires_restart' => true,
            'sync' => $this->syncMetadata($server),
        ];
    }

    public function uninstall(Server $server, int $publishedFileId): void
    {
        $this->assertWorkshopEnabled($server);
        $appId = $this->resolveAppId($server);

        $deleted = ServerWorkshopItem::query()
            ->where('server_id', $server->id)
            ->where('published_file_id', $publishedFileId)
            ->delete();

        if (!$deleted) {
            throw new DisplayException('Item Workshop não encontrado neste servidor.');
        }

        $this->syncServerFiles($server, $appId);
    }

    public function resolveAppId(Server $server): int
    {
        $server->loadMissing(['variables', 'egg.configFrom']);

        $eggAppId = app(EggWorkshopFeatureService::class)->resolveWorkshopAppId($server->egg);
        if ($eggAppId) {
            return $eggAppId;
        }

        foreach (['WORKSHOP_APPID', 'SRCDS_APPID', 'STEAM_APPID', 'APP_ID'] as $env) {
            $variable = $server->variables->firstWhere('env_variable', $env);
            $value = $variable?->server_value ?? $variable?->default_value;
            if (!empty($value) && is_numeric($value)) {
                $appId = (int) $value;

                if ($env === 'WORKSHOP_APPID') {
                    return $appId;
                }

                return self::WORKSHOP_CONSUMER_APP_IDS[$appId] ?? $appId;
            }
        }

        throw new DisplayException('Não foi possível determinar o App ID Steam deste servidor. Configure o Workshop App ID no egg.');
    }

    public function assertWorkshopEnabled(Server $server): void
    {
        $server->loadMissing('egg');
        $features = $server->egg->inherit_features ?? [];

        if (!in_array(Egg::FEATURE_WORKSHOP, $features, true)) {
            throw new DisplayException('Workshop não está disponível para este tipo de servidor.');
        }

        if (!$this->steamWorkshopService->isConfigured()) {
            throw new DisplayException('A Steam Web API Key não está configurada. Contacte o administrador do painel.');
        }
    }

    /**
     * @return array{id: string, label: string, description: string}
     */
    private function syncMetadata(Server $server): array
    {
        $driver = $this->workshopSyncManager->driverForServer($server);

        return [
            'id' => $driver->id(),
            'label' => $driver->label(),
            'description' => $driver->description(),
        ];
    }

    private function syncServerFiles(Server $server, int $appId): void
    {
        $this->workshopSyncManager->sync($server, $appId);
    }
}
