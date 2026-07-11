<?php

namespace Pterodactyl\Services\Steam;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerWorkshopItem;
use Pterodactyl\Exceptions\DisplayException;

class WorkshopService
{
    public function __construct(
        private SteamWorkshopService $steamWorkshopService,
        private GmodWorkshopSyncService $gmodWorkshopSyncService,
    ) {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int, next_cursor: string|null, app_id: int}
     */
    public function browse(Server $server, string $search, string $sort, ?string $cursor, int $perPage): array
    {
        $this->assertWorkshopEnabled($server);
        $appId = $this->resolveAppId($server);

        $result = $this->steamWorkshopService->browse($appId, $search, $sort, $cursor, $perPage);
        $result['app_id'] = $appId;

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
     * @return array<int, array<string, mixed>>
     */
    public function installed(Server $server): array
    {
        $this->assertWorkshopEnabled($server);

        return ServerWorkshopItem::query()
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
        $server->loadMissing('variables');

        foreach (['SRCDS_APPID', 'STEAM_APPID', 'APP_ID'] as $env) {
            $variable = $server->variables->firstWhere('env_variable', $env);
            $value = $variable?->server_value ?? $variable?->default_value;
            if (!empty($value) && is_numeric($value)) {
                return (int) $value;
            }
        }

        throw new DisplayException('Não foi possível determinar o App ID Steam deste servidor.');
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

    private function syncServerFiles(Server $server, int $appId): void
    {
        $server->loadMissing('egg');
        $eggName = strtolower($server->egg->name ?? '');

        if (str_contains($eggName, 'garry') || str_contains($eggName, 'gmod')) {
            $this->gmodWorkshopSyncService->sync($server, $appId);

            return;
        }

        throw new DisplayException('Sincronização automática de Workshop ainda não está implementada para este jogo.');
    }
}
