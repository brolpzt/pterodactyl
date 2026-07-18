<?php

namespace Pterodactyl\Services\Steam\Workshop\Drivers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerWorkshopItem;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Servers\EnvironmentService;
use Pterodactyl\Services\Steam\SteamWorkshopService;
use Pterodactyl\Services\Steam\SteamWorkshopCredentialsService;
use Pterodactyl\Services\Steam\Workshop\WorkshopSyncDriver;
use Pterodactyl\Services\Steam\Workshop\WorkshopAddonScriptRunner;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class L4d2VpkWorkshopDriver implements WorkshopSyncDriver
{
    private const MANIFEST_FILE = '.pterodactyl_workshop_manifest';

    public function __construct(
        private SteamWorkshopService $steamWorkshopService,
        private SteamWorkshopCredentialsService $credentialsService,
        private EnvironmentService $environmentService,
        private WorkshopAddonScriptRunner $scriptRunner,
        private DaemonFileRepository $fileRepository,
    ) {
    }

    public function id(): string
    {
        return Egg::WORKSHOP_SYNC_L4D2_VPK;
    }

    public function label(): string
    {
        return 'Left 4 Dead 2 (VPK em addons/workshop/)';
    }

    public function description(): string
    {
        return 'Descarrega mods via URL pública da Steam Workshop (file_url) e grava .vpk em left4dead2/addons/workshop/. Não precisa de conta Steam na maioria dos casos; SteamCMD só é usado como fallback. Reinicie o servidor após a sync.';
    }

    public function sync(Server $server, int $workshopAppId): void
    {
        $gameDir = $this->resolveGameDirectory($server);
        $this->assertGameDirectoryExists($server, $gameDir);

        $itemIds = $this->resolveInstalledItemIds($server, $workshopAppId);
        $httpDownloads = $this->resolveHttpDownloads($itemIds);
        $steamCmdIds = array_values(array_diff($itemIds, array_keys($httpDownloads)));

        $credentials = $this->credentialsService->forServer($server);
        $steamUser = $credentials['user'];
        $steamPass = $credentials['pass'];
        $steamAuth = $credentials['auth'];

        if ($steamCmdIds !== [] && $steamUser === '') {
            $missing = implode(', ', $steamCmdIds);
            throw new DisplayException(
                "Alguns itens Workshop não têm URL de download direta ({$missing}). Configure uma conta Steam em Admin → Steam Workshop (ou STEAM_USER no Startup) para o fallback SteamCMD."
            );
        }

        $this->scriptRunner->run(
            $server,
            $this->buildSyncScript(
                $gameDir,
                $workshopAppId,
                $httpDownloads,
                $steamCmdIds,
                $steamUser,
                $steamPass,
                $steamAuth
            )
        );
    }

    /**
     * @return int[]
     */
    private function resolveInstalledItemIds(Server $server, int $workshopAppId): array
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

        $ids = array_keys($resolvedIds);
        sort($ids);

        return $ids;
    }

    /**
     * @param  int[]  $itemIds
     * @return array<int, array{url: string, time_updated: int}>
     */
    private function resolveHttpDownloads(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $downloads = [];
        foreach ($this->steamWorkshopService->getDownloadDetails($itemIds) as $detail) {
            $url = $detail['file_url'] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }

            $downloads[(int) $detail['published_file_id']] = [
                'url' => $url,
                'time_updated' => (int) ($detail['time_updated'] ?? 0),
            ];
        }

        return $downloads;
    }

    private function resolveGameDirectory(Server $server): string
    {
        $server->loadMissing('variables');
        $configured = strtolower(trim((string) ($this->environmentService->handle($server)['SRCDS_GAME'] ?? 'left4dead2')));
        $sanitized = preg_replace('/[^a-z0-9_\\-]/', '', $configured) ?? '';

        return $sanitized !== '' ? $sanitized : 'left4dead2';
    }

    private function assertGameDirectoryExists(Server $server, string $gameDir): void
    {
        try {
            $this->fileRepository->setServer($server)->getDirectory($gameDir);
        } catch (DaemonConnectionException) {
            throw new DisplayException(
                "Diretório do jogo \"{$gameDir}\" não encontrado no servidor. Verifique se o Left 4 Dead 2 está instalado e se SRCDS_GAME está correto."
            );
        }
    }

    /**
     * @param  array<int, array{url: string, time_updated: int}>  $httpDownloads
     * @param  int[]  $steamCmdIds
     */
    private function buildSyncScript(
        string $gameDir,
        int $workshopAppId,
        array $httpDownloads,
        array $steamCmdIds,
        string $steamUser,
        string $steamPass,
        string $steamAuth,
    ): string {
        $addonsDir = $gameDir . '/addons/workshop';
        $legacyAddonsDir = $gameDir . '/addons';
        $manifestPath = $addonsDir . '/' . self::MANIFEST_FILE;
        $legacyManifestPath = $legacyAddonsDir . '/' . self::MANIFEST_FILE;

        $httpEntries = [];
        foreach ($httpDownloads as $itemId => $meta) {
            $httpEntries[] = sprintf(
                '%d|%d|%s',
                (int) $itemId,
                (int) $meta['time_updated'],
                $meta['url']
            );
        }

        $lines = [
            '#!/bin/bash',
            'set -euo pipefail',
            'cd /mnt/server',
            'export HOME=/mnt/server',
            '',
            'ADDONS_DIR=' . escapeshellarg($addonsDir),
            'LEGACY_ADDONS_DIR=' . escapeshellarg($legacyAddonsDir),
            'MANIFEST=' . escapeshellarg($manifestPath),
            'LEGACY_MANIFEST=' . escapeshellarg($legacyManifestPath),
            'WORKSHOP_APP_ID=' . (int) $workshopAppId,
            'STEAM_USER=' . escapeshellarg($steamUser),
            'STEAM_PASS=' . escapeshellarg($steamPass),
            'STEAM_AUTH=' . escapeshellarg($steamAuth),
            'HTTP_ENTRIES=(' . implode(' ', array_map('escapeshellarg', $httpEntries)) . ')',
            'STEAMCMD_IDS=(' . implode(' ', array_map(fn (int $id) => (string) $id, $steamCmdIds)) . ')',
            '',
            'mkdir -p "${ADDONS_DIR}"',
            'mkdir -p steamapps',
            '',
            '# Migrate away from legacy left4dead2/addons/ (pre-workshop/ path).',
            'if [ -f "${LEGACY_MANIFEST}" ]; then',
            '    while IFS= read -r line || [ -n "${line}" ]; do',
            '        [ -z "${line}" ] && continue',
            '        vpk="${line%%|*}"',
            '        rm -f "${LEGACY_ADDONS_DIR}/${vpk}"',
            '    done < "${LEGACY_MANIFEST}"',
            '    rm -f "${LEGACY_MANIFEST}"',
            'fi',
            '',
            '# Remove VPKs previously managed by the panel in addons/workshop/.',
            'if [ -f "${MANIFEST}" ]; then',
            '    while IFS= read -r line || [ -n "${line}" ]; do',
            '        [ -z "${line}" ] && continue',
            '        # Support legacy "filename" lines and "filename|item_id|time_updated".',
            '        vpk="${line%%|*}"',
            '        rm -f "${ADDONS_DIR}/${vpk}"',
            '    done < "${MANIFEST}"',
            'fi',
            ': > "${MANIFEST}"',
            '',
            'download_http_item() {',
            '    local item_id="$1"',
            '    local time_updated="$2"',
            '    local url="$3"',
            '    local dest_name="workshop_${item_id}.vpk"',
            '    local tmp="${ADDONS_DIR}/.${dest_name}.tmp"',
            '',
            '    echo "A descarregar Workshop item ${item_id} via HTTP..."',
            '    if ! curl -fsSL --retry 3 --retry-delay 2 -o "${tmp}" "${url}"; then',
            '        rm -f "${tmp}"',
            '        echo "Erro no download HTTP do item ${item_id}."',
            '        return 1',
            '    fi',
            '',
            '    # Reject empty / HTML error pages.',
            '    if [ ! -s "${tmp}" ]; then',
            '        rm -f "${tmp}"',
            '        echo "Download vazio para o item ${item_id}."',
            '        return 1',
            '    fi',
            '',
            '    mv -f "${tmp}" "${ADDONS_DIR}/${dest_name}"',
            '    echo "${dest_name}|${item_id}|${time_updated}" >> "${MANIFEST}"',
            '    echo "Copiado ${dest_name}"',
            '}',
            '',
            'ensure_steamcmd() {',
            '    STEAMCMD="./steamcmd/steamcmd.sh"',
            '    if [ -x "${STEAMCMD}" ]; then',
            '        return 0',
            '    fi',
            '    echo "steamcmd não encontrado — a instalar..."',
            '    mkdir -p steamcmd',
            '    curl -fsSL -o /tmp/steamcmd.tar.gz https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz',
            '    tar -xzf /tmp/steamcmd.tar.gz -C steamcmd',
            '    chmod +x "${STEAMCMD}"',
            '}',
            '',
            'copy_vpks_for_item() {',
            '    local item_id="$1"',
            '    local content_dir="steamapps/workshop/content/${WORKSHOP_APP_ID}/${item_id}"',
            '    local copied=0',
            '',
            '    if [ ! -d "${content_dir}" ]; then',
            '        echo "Aviso: conteúdo Workshop ${item_id} não encontrado após download SteamCMD."',
            '        return 0',
            '    fi',
            '',
            '    while IFS= read -r -d "" vpk; do',
            '        base="$(basename "${vpk}")"',
            '        dest_name="workshop_${item_id}_${base}"',
            '        cp -f "${vpk}" "${ADDONS_DIR}/${dest_name}"',
            '        echo "${dest_name}|${item_id}|0" >> "${MANIFEST}"',
            '        copied=1',
            '        echo "Copiado ${dest_name}"',
            '    done < <(find "${content_dir}" -type f -iname "*.vpk" -print0)',
            '',
            '    if [ "${copied}" -eq 0 ]; then',
            '        echo "Aviso: nenhum .vpk encontrado para o item ${item_id}."',
            '    fi',
            '}',
            '',
        ];

        if ($httpDownloads === [] && $steamCmdIds === []) {
            $lines[] = 'echo "Nenhum mod Workshop configurado — VPKs geridos pelo painel removidos."';
        } else {
            $lines[] = 'set +e';
            $lines[] = 'if [ "${#HTTP_ENTRIES[@]}" -gt 0 ]; then';
            $lines[] = '    for ENTRY in "${HTTP_ENTRIES[@]}"; do';
            $lines[] = '        ITEM_ID="${ENTRY%%|*}"';
            $lines[] = '        REST="${ENTRY#*|}"';
            $lines[] = '        TIME_UPDATED="${REST%%|*}"';
            $lines[] = '        URL="${REST#*|}"';
            $lines[] = '        download_http_item "${ITEM_ID}" "${TIME_UPDATED}" "${URL}"';
            $lines[] = '    done';
            $lines[] = 'fi';
            $lines[] = '';
            $lines[] = 'if [ "${#STEAMCMD_IDS[@]}" -gt 0 ]; then';
            $lines[] = '    ensure_steamcmd';
            $lines[] = '    for ITEM_ID in "${STEAMCMD_IDS[@]}"; do';
            $lines[] = '        echo "A descarregar Workshop item ${ITEM_ID} via SteamCMD (fallback)..."';
            $lines[] = '        "${STEAMCMD}" +force_install_dir /mnt/server +login "${STEAM_USER}" "${STEAM_PASS}" "${STEAM_AUTH}" +workshop_download_item "${WORKSHOP_APP_ID}" "${ITEM_ID}" validate +quit';
            $lines[] = '        if [ $? -ne 0 ]; then';
            $lines[] = '            echo "Erro ao descarregar item ${ITEM_ID} via SteamCMD. Verifique STEAM_USER/STEAM_PASS e se a conta subscreveu o mod."';
            $lines[] = '            continue';
            $lines[] = '        fi';
            $lines[] = '        copy_vpks_for_item "${ITEM_ID}"';
            $lines[] = '    done';
            $lines[] = 'fi';
            $lines[] = 'set -e';
        }

        $lines[] = '';
        $lines[] = 'echo "Sincronização L4D2 Workshop concluída."';

        return implode("\n", $lines) . "\n";
    }
}
