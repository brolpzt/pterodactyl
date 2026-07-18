<?php

namespace Pterodactyl\Services\Steam;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;

class SteamWorkshopService
{
    public const SORT_TRENDING = 3;

    public const SORT_MOST_SUBSCRIBED = 0;

    public const SORT_RECENT = 1;

    public const SORT_TEXT_SEARCH = 12;

    private const QUERY_URL = 'https://api.steampowered.com/IPublishedFileService/QueryFiles/v1/';

    private const DETAILS_URL = 'https://api.steampowered.com/IPublishedFileService/GetDetails/v1/';

    /** Public Remote Storage details endpoint — returns direct file_url for downloadable Workshop content (e.g. L4D2 VPKs). */
    private const REMOTE_STORAGE_DETAILS_URL = 'https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/';

    /**
     * Browse workshop items for a given Steam app.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, next_cursor: string|null}
     */
    public function browse(int $appId, string $search = '', string $sort = 'trending', ?string $cursor = null, int $perPage = 30): array
    {
        $apiKey = $this->getApiKey();

        $queryType = match (true) {
            $search !== '' => self::SORT_TEXT_SEARCH,
            $sort === 'recent' => self::SORT_RECENT,
            $sort === 'popular' => self::SORT_MOST_SUBSCRIBED,
            default => self::SORT_TRENDING,
        };

        $cursor = $cursor ?: '*';
        $cacheKey = sprintf(
            'steam:workshop:browse:%d:%s:%s:%s:%d',
            $appId,
            $queryType,
            md5($search),
            $cursor,
            $perPage
        );

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($apiKey, $appId, $search, $queryType, $cursor, $perPage) {
            $response = Http::timeout(20)->get(self::QUERY_URL, [
                'key' => $apiKey,
                'query_type' => $queryType,
                'page' => 1,
                'cursor' => $cursor,
                'numperpage' => min(max($perPage, 1), 50),
                'creator_appid' => $appId,
                'appid' => $appId,
                'search_text' => $search,
                'requiredtags' => '',
                'excludedtags' => '',
                'match_all_tags' => false,
                'required_flags' => '',
                'omitted_flags' => '',
                'filetype' => 0,
                'child_publishedfileid' => 0,
                'days' => 7,
                'include_recent_votes_only' => false,
                'cache_max_age_seconds' => 600,
                'language' => 0,
                'admin_query' => false,
                'return_vote_data' => true,
                'return_tags' => true,
                'return_kv_tags' => false,
                'return_previews' => true,
                'return_children' => true,
                'return_short_description' => true,
                'return_for_sale_data' => false,
                'return_metadata' => false,
                'return_playtime_stats' => 0,
                'return_details' => true,
                'strip_description_bbcode' => true,
            ]);

            if (!$response->successful()) {
                throw new DisplayException('Não foi possível consultar a Steam Workshop. Tente novamente em instantes.');
            }

            $payload = $response->json('response') ?? [];
            $items = array_values(array_filter(array_map(
                fn (array $item) => $this->normalizeItem($item),
                $payload['publishedfiledetails'] ?? []
            ), fn (array $item) => $item['published_file_id'] !== ''));

            return [
                'items' => $items,
                'total' => (int) ($payload['total'] ?? count($items)),
                'next_cursor' => $payload['next_cursor'] ?? null,
            ];
        });
    }

    /**
     * Fetch details for one or more published file IDs.
     *
     * @param  int[]  $fileIds
     * @return array<int, array<string, mixed>>
     */
    public function getDetails(int $appId, array $fileIds): array
    {
        $fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds))));
        if ($fileIds === []) {
            return [];
        }

        $apiKey = $this->getApiKey();
        $cacheKey = 'steam:workshop:details:' . $appId . ':' . md5(implode(',', $fileIds));

        return Cache::remember($cacheKey, now()->addHour(), function () use ($apiKey, $appId, $fileIds) {
            $params = [
                'key' => $apiKey,
                'appid' => $appId,
                'includetags' => true,
                'includeadditionalpreviews' => true,
                'includechildren' => true,
                'includekvtags' => false,
                'includevotes' => true,
                'short_description' => true,
                'includeforsaledata' => false,
                'includemetadata' => false,
                'return_playtime_stats' => 0,
                'strip_description_bbcode' => true,
                'admin_query' => false,
            ];

            foreach ($fileIds as $index => $fileId) {
                $params["publishedfileids[{$index}]"] = $fileId;
            }

            $response = Http::timeout(20)->get(self::DETAILS_URL, $params);
            if (!$response->successful()) {
                throw new DisplayException('Não foi possível obter detalhes do item Workshop.');
            }

            $items = $response->json('response.publishedfiledetails') ?? [];

            return array_map(fn (array $item) => $this->normalizeItem($item), $items);
        });
    }

    /**
     * Expand a collection into individual workshop item IDs.
     *
     * @return int[]
     */
    public function resolveItemIds(int $appId, int $publishedFileId): array
    {
        $details = $this->getDetails($appId, [$publishedFileId]);
        $item = $details[0] ?? null;
        if (!$item) {
            throw new DisplayException('Item Workshop não encontrado ou indisponível.');
        }

        if ($item['is_collection'] && !empty($item['children'])) {
            return array_map('intval', $item['children']);
        }

        return [$publishedFileId];
    }

    /**
     * Resolve direct download URLs for Workshop items via ISteamRemoteStorage.
     * Used by games (notably L4D2) whose dedicated servers cannot mount Workshop natively.
     *
     * @param  int[]  $fileIds
     * @return array<int, array{published_file_id: int, title: string, file_url: string|null, filename: string|null, time_updated: int|null, file_size: int}>
     */
    public function getDownloadDetails(array $fileIds): array
    {
        $fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds))));
        if ($fileIds === []) {
            return [];
        }

        $byId = [];
        foreach (array_chunk($fileIds, 50) as $chunk) {
            $payload = ['itemcount' => count($chunk)];
            foreach ($chunk as $index => $fileId) {
                $payload["publishedfileids[{$index}]"] = $fileId;
            }

            $response = Http::asForm()->timeout(30)->post(self::REMOTE_STORAGE_DETAILS_URL, $payload);
            if (!$response->successful()) {
                throw new DisplayException('Não foi possível obter URLs de download dos itens Workshop.');
            }

            foreach ($response->json('response.publishedfiledetails') ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $id = (int) ($item['publishedfileid'] ?? 0);
                if ($id <= 0 || (int) ($item['result'] ?? 0) !== 1) {
                    continue;
                }

                $fileUrl = trim((string) ($item['file_url'] ?? ''));
                $byId[$id] = [
                    'published_file_id' => $id,
                    'title' => (string) ($item['title'] ?? 'Sem título'),
                    'file_url' => $fileUrl !== '' ? $fileUrl : null,
                    'filename' => isset($item['filename']) && $item['filename'] !== ''
                        ? (string) $item['filename']
                        : null,
                    'time_updated' => isset($item['time_updated']) ? (int) $item['time_updated'] : null,
                    'file_size' => (int) ($item['file_size'] ?? 0),
                ];
            }
        }

        $ordered = [];
        foreach ($fileIds as $fileId) {
            if (isset($byId[$fileId])) {
                $ordered[] = $byId[$fileId];
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        $preview = $item['preview_url'] ?? null;
        if (!$preview && !empty($item['previews'][0]['preview_url'])) {
            $preview = $item['previews'][0]['preview_url'];
        }

        $children = [];
        if (!empty($item['children']) && is_array($item['children'])) {
            foreach ($item['children'] as $child) {
                if (is_array($child) && !empty($child['publishedfileid'])) {
                    $children[] = (string) $child['publishedfileid'];
                } elseif (is_scalar($child)) {
                    $children[] = (string) $child;
                }
            }
        }

        $fileType = (int) ($item['file_type'] ?? $item['filetype'] ?? 0);
        $fileUrl = trim((string) ($item['file_url'] ?? ''));

        return [
            'published_file_id' => (string) ($item['publishedfileid'] ?? ''),
            'title' => (string) ($item['title'] ?? 'Sem título'),
            'description' => (string) ($item['short_description'] ?? $item['file_description'] ?? ''),
            'preview_url' => $preview,
            'file_url' => $fileUrl !== '' ? $fileUrl : null,
            'file_size' => (int) ($item['file_size'] ?? 0),
            'votes_up' => (int) ($item['votes_up'] ?? 0),
            'time_updated' => isset($item['time_updated']) ? (int) $item['time_updated'] : null,
            'creator' => (string) ($item['creator'] ?? ''),
            'tags' => array_values(array_filter(array_map(
                fn ($tag) => is_array($tag) ? ($tag['tag'] ?? null) : null,
                $item['tags'] ?? []
            ))),
            'is_collection' => $fileType === 2 || !empty($children),
            'children' => $children,
            'workshop_url' => !empty($item['publishedfileid'])
                ? 'https://steamcommunity.com/sharedfiles/filedetails/?id=' . $item['publishedfileid']
                : null,
        ];
    }

    public function isConfigured(): bool
    {
        return !empty($this->getApiKey(false));
    }

    private function getApiKey(bool $required = true): string
    {
        $key = (string) config('pterodactyl.steam.api_key', '');
        if ($key === '' && $required) {
            throw new DisplayException('A Steam Web API Key não está configurada. Contacte o administrador do painel.');
        }

        return $key;
    }
}
