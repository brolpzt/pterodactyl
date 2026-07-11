<?php

namespace Pterodactyl\Services\Steam\Workshop\Drivers;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerWorkshopItem;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Servers\EnvironmentService;
use Pterodactyl\Services\Steam\SteamWorkshopService;
use Pterodactyl\Services\Steam\Workshop\WorkshopSyncDriver;
use Pterodactyl\Services\Steam\Workshop\WorkshopAddonScriptRunner;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;

class L4d2VpkWorkshopDriver implements WorkshopSyncDriver
{
    private const MANIFEST_FILE = '.pterodactyl_workshop_manifest';

    public function __construct(
        private SteamWorkshopService $steamWorkshopService,
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
        return 'Left 4 Dead 2 (VPK em addons/)';
    }

    public function description(): string
    {
        return 'Descarrega mods via SteamCMD e copia ficheiros .vpk para left4dead2/addons/. Requer STEAM_USER/STEAM_PASS em Startup com uma conta subscrita aos mods. Reinicie o servidor após a sync.';
    }

    public function sync(Server $server, int $workshopAppId): void
    {
        $server->loadMissing('variables');

        $environment = $this->environmentService->handle($server);
        $steamUser = trim((string) ($environment['STEAM_USER'] ?? ''));
        $steamPass = (string) ($environment['STEAM_PASS'] ?? '');
        $steamAuth = (string) ($environment['STEAM_AUTH'] ?? '');

        if ($steamUser === '') {
            throw new DisplayException(
                'Configure Steam Username (STEAM_USER) em Startup com uma conta Steam que tenha subscrito os mods Workshop. O L4D2 não consegue descarregar mods com login anónimo.'
            );
        }

        $gameDir = $this->resolveGameDirectory($environment);
        $this->assertGameDirectoryExists($server, $gameDir);

        $itemIds = $this->resolveInstalledItemIds($server, $workshopAppId);

        $this->scriptRunner->run(
            $server,
            $this->buildSyncScript(
                $gameDir,
                $workshopAppId,
                $itemIds,
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
     * @param  array<string, mixed>  $environment
     */
    private function resolveGameDirectory(array $environment): string
    {
        $configured = strtolower(trim((string) ($environment['SRCDS_GAME'] ?? 'left4dead2')));
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
     * @param  int[]  $itemIds
     */
    private function buildSyncScript(
        string $gameDir,
        int $workshopAppId,
        array $itemIds,
        string $steamUser,
        string $steamPass,
        string $steamAuth,
    ): string {
        $addonsDir = $gameDir . '/addons';
        $manifestPath = $addonsDir . '/' . self::MANIFEST_FILE;

        $lines = [
            '#!/bin/bash',
            'set -euo pipefail',
            'cd /mnt/server',
            'export HOME=/mnt/server',
            '',
            'ADDONS_DIR=' . escapeshellarg($addonsDir),
            'MANIFEST=' . escapeshellarg($manifestPath),
            'WORKSHOP_APP_ID=' . (int) $workshopAppId,
            'STEAM_USER=' . escapeshellarg($steamUser),
            'STEAM_PASS=' . escapeshellarg($steamPass),
            'STEAM_AUTH=' . escapeshellarg($steamAuth),
            'ITEM_IDS=(' . implode(' ', array_map(fn (int $id) => (string) $id, $itemIds)) . ')',
            '',
            'mkdir -p "${ADDONS_DIR}"',
            'mkdir -p steamapps',
            '',
            'if [ -f "${MANIFEST}" ]; then',
            '    while IFS= read -r vpk || [ -n "${vpk}" ]; do',
            '        [ -z "${vpk}" ] && continue',
            '        rm -f "${ADDONS_DIR}/${vpk}"',
            '    done < "${MANIFEST}"',
            'fi',
            ': > "${MANIFEST}"',
            '',
            'STEAMCMD="./steamcmd/steamcmd.sh"',
            'if [ ! -x "${STEAMCMD}" ]; then',
            '    echo "steamcmd não encontrado — a instalar..."',
            '    mkdir -p steamcmd',
            '    curl -fsSL -o /tmp/steamcmd.tar.gz https://steamcdn-a.akamaihd.net/client/installer/steamcmd_linux.tar.gz',
            '    tar -xzf /tmp/steamcmd.tar.gz -C steamcmd',
            '    chmod +x "${STEAMCMD}"',
            'fi',
            '',
            'copy_vpks_for_item() {',
            '    local item_id="$1"',
            '    local content_dir="steamapps/workshop/content/${WORKSHOP_APP_ID}/${item_id}"',
            '    local copied=0',
            '',
            '    if [ ! -d "${content_dir}" ]; then',
            '        echo "Aviso: conteúdo Workshop ${item_id} não encontrado após download."',
            '        return 0',
            '    fi',
            '',
            '    while IFS= read -r -d "" vpk; do',
            '        base="$(basename "${vpk}")"',
            '        dest_name="workshop_${item_id}_${base}"',
            '        cp -f "${vpk}" "${ADDONS_DIR}/${dest_name}"',
            '        echo "${dest_name}" >> "${MANIFEST}"',
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

        if ($itemIds === []) {
            $lines[] = 'echo "Nenhum mod Workshop configurado — VPKs geridos pelo painel removidos."';
        } else {
            $lines[] = 'set +e';
            $lines[] = 'for ITEM_ID in "${ITEM_IDS[@]}"; do';
            $lines[] = '    echo "A descarregar Workshop item ${ITEM_ID}..."';
            $lines[] = '    "${STEAMCMD}" +force_install_dir /mnt/server +login "${STEAM_USER}" "${STEAM_PASS}" "${STEAM_AUTH}" +workshop_download_item "${WORKSHOP_APP_ID}" "${ITEM_ID}" validate +quit';
            $lines[] = '    if [ $? -ne 0 ]; then';
            $lines[] = '        echo "Erro ao descarregar item ${ITEM_ID}. Verifique STEAM_USER/STEAM_PASS e se a conta subscreveu o mod."';
            $lines[] = '        continue';
            $lines[] = '    fi';
            $lines[] = '    copy_vpks_for_item "${ITEM_ID}"';
            $lines[] = 'done';
            $lines[] = 'set -e';
        }

        $lines[] = '';
        $lines[] = 'echo "Sincronização L4D2 Workshop concluída."';

        return implode("\n", $lines) . "\n";
    }
}
