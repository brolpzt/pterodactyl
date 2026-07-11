<?php

namespace Pterodactyl\Services\Amxx;

use Illuminate\Http\Response;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Exceptions\Service\Amxx\AmxxException;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonServerPlayersRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use GuzzleHttp\Exception\ClientException;

class AmxxService
{
    public const ACCESS_FLAG_LABELS = [
        'a' => 'Imunidade',
        'b' => 'Reserva de slot',
        'c' => 'Kick (amx_kick)',
        'd' => 'Ban (amx_ban)',
        'e' => 'Slay/Slap',
        'f' => 'Trocar mapa (amx_map)',
        'g' => 'Cvars (amx_cvar)',
        'h' => 'Config (amx_cfg)',
        'i' => 'Chat admin',
        'j' => 'Votações',
        'k' => 'sv_password',
        'l' => 'RCON (amx_rcon)',
        'm' => 'Nível custom A',
        'n' => 'Nível custom B',
        'o' => 'Nível custom C',
        'p' => 'Nível custom D',
        'q' => 'Nível custom E',
        'r' => 'Nível custom F',
        's' => 'Nível custom G',
        't' => 'Nível custom H',
        'u' => 'Menu AMXX',
    ];

    public const PRESET_FLAGS = [
        'owner' => 'abcdefghijklmnopqrstu',
        'admin' => 'bcdefijklmnopqrstu',
        'mod' => 'bcdj',
    ];

    public function __construct(
        private DaemonFileRepository $fileRepository,
        private DaemonCommandRepository $commandRepository,
        private DaemonServerPlayersRepository $playersRepository,
    ) {
    }

    /** @var array<int, bool|null> */
    private array $wingsReachableCache = [];

    /** @var array<int, array<string, array|null>> */
    private array $directoryListingCache = [];

    /** @var array<int, array<string, mixed>> */
    private array $resolvedPathsCache = [];

    /** @var array<int, string> */
    private array $gameDirectoryCache = [];

    /** @var array<int, string> */
    private array $configuredGameDirectoryCache = [];

    public function adminsPage(Server $server): array
    {
        $overview = $this->overview($server);
        $admins = [];

        if ($overview['wings_reachable'] && $overview['amxx_installed'] && $overview['users_ini_exists']) {
            $content = $this->readFile($server, $overview['paths']['users_ini']);
            $admins = $this->parseUsersIni($content)['admins'];
        }

        return [
            'overview' => $overview,
            'admins' => $admins,
        ];
    }

    public function overview(Server $server): array
    {
        $paths = $this->resolvePaths($server);
        $wingsReachable = $this->wingsReachable($server);
        $gameListing = $wingsReachable ? $this->getDirectoryListing($server, $paths['game_dir']) : null;
        $configsListing = $wingsReachable
            ? $this->getDirectoryListing($server, $paths['game_dir'] . '/addons/amxmodx/configs')
            : null;

        return [
            'game_directory' => $paths['game_dir'],
            'wings_reachable' => $wingsReachable,
            'amxx_installed' => $wingsReachable && $this->directoryExists($server, $paths['game_dir'] . '/addons/amxmodx'),
            'users_ini_exists' => $wingsReachable
                && $configsListing !== null
                && $this->fileExistsInDirectoryListing($configsListing, 'users.ini'),
            'banned_cfg_exists' => $wingsReachable
                && $gameListing !== null
                && $this->fileExistsInDirectoryListing($gameListing, 'banned.cfg'),
            'listip_cfg_exists' => $wingsReachable
                && $gameListing !== null
                && $this->fileExistsInDirectoryListing($gameListing, 'listip.cfg'),
            'paths' => $paths,
            'presets' => array_keys(self::PRESET_FLAGS),
            'access_flags' => self::ACCESS_FLAG_LABELS,
        ];
    }

    public function listAdmins(Server $server): array
    {
        return $this->adminsPage($server)['admins'];
    }

    public function listPlayers(Server $server): array
    {
        try {
            return $this->playersRepository->setServer($server)->list();
        } catch (DaemonConnectionException $exception) {
            if (!$this->wingsReachable($server)) {
                throw new AmxxException(
                    'Não foi possível comunicar com o node Wings deste servidor.',
                    Response::HTTP_BAD_GATEWAY,
                    $exception
                );
            }

            $previous = $exception->getPrevious();
            if ($previous instanceof ClientException && $previous->getResponse()->getStatusCode() === Response::HTTP_BAD_GATEWAY) {
                throw new AmxxException(
                    'O servidor precisa estar online para listar jogadores.',
                    Response::HTTP_BAD_GATEWAY,
                    $exception
                );
            }

            throw new AmxxException(
                'Não foi possível obter a lista de jogadores via consola.',
                Response::HTTP_BAD_GATEWAY,
                $exception
            );
        }
    }

    public function kickPlayer(Server $server, int $userid, ?string $reason = null): array
    {
        if ($userid < 1) {
            throw new AmxxException('O userid do jogador é inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $command = 'amx_kick #' . $userid;
        if ($reason !== null && trim($reason) !== '') {
            $command .= ' "' . $this->sanitizeComment($reason) . '"';
        }

        if (!$this->trySendCommand($server, $command)) {
            throw new AmxxException(
                'Não foi possível enviar o comando de kick. Verifique se o servidor está online.',
                Response::HTTP_BAD_GATEWAY
            );
        }

        return [
            'userid' => $userid,
            'command_sent' => true,
        ];
    }

    public function createAdmin(
        Server $server,
        string $authType,
        string $auth,
        string $password,
        string $accessFlags,
        ?string $nickname = null,
        bool $reload = true,
    ): array {
        $auth = trim($auth);
        $accessFlags = $this->normalizeAccessFlags($accessFlags);
        $accountFlags = $this->resolveAccountFlags($authType, $password);

        if ($auth === '') {
            throw new AmxxException('O identificador de autenticação é obrigatório.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($authType === 'steamid' && !preg_match('/^STEAM_[0-5]:[01]:\d+$/i', $auth)) {
            throw new AmxxException('SteamID inválido. Use o formato STEAM_0:X:XXXXX.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($authType === 'ip' && !filter_var($auth, FILTER_VALIDATE_IP)) {
            throw new AmxxException('Endereço IP inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $paths = $this->resolvePaths($server);
        $content = $this->readFile($server, $paths['users_ini']);
        $parsed = $this->parseUsersIni($content);

        foreach ($parsed['admins'] as $admin) {
            if (!$admin['enabled']) {
                continue;
            }

            if (strcasecmp($admin['auth'], $auth) === 0) {
                throw new AmxxException('Já existe um admin com este identificador.', Response::HTTP_CONFLICT);
            }
        }

        $comment = $nickname ? '; ' . $this->sanitizeComment($nickname) : '';
        $line = $this->formatAdminLine($auth, $password, $accessFlags, $accountFlags, $comment);

        $parsed['admins'][] = [
            'id' => count($parsed['admins']),
            'auth' => $auth,
            'password' => $password,
            'access_flags' => $accessFlags,
            'account_flags' => $accountFlags,
            'auth_type' => $authType,
            'nickname' => $nickname,
            'enabled' => true,
            'comment' => $comment ? ltrim($comment, '; ') : null,
            'line' => $line,
        ];

        $this->writeUsersIni($server, $paths['users_ini'], $parsed);
        $commandSent = $reload ? $this->trySendCommand($server, 'amx_reloadadmins') : false;

        return [
            'admin' => end($parsed['admins']),
            'command_sent' => $commandSent,
        ];
    }

    public function updateAdmin(
        Server $server,
        int $id,
        string $authType,
        string $auth,
        string $password,
        string $accessFlags,
        ?string $nickname = null,
        bool $enabled = true,
        bool $reload = true,
    ): array {
        $auth = trim($auth);
        $accessFlags = $this->normalizeAccessFlags($accessFlags);
        $accountFlags = $this->resolveAccountFlags($authType, $password);

        $paths = $this->resolvePaths($server);
        $content = $this->readFile($server, $paths['users_ini']);
        $parsed = $this->parseUsersIni($content);

        if (!isset($parsed['admins'][$id])) {
            throw new AmxxException('Admin não encontrado.', Response::HTTP_NOT_FOUND);
        }

        foreach ($parsed['admins'] as $index => $admin) {
            if ($index === $id || !$admin['enabled']) {
                continue;
            }

            if (strcasecmp($admin['auth'], $auth) === 0) {
                throw new AmxxException('Já existe outro admin com este identificador.', Response::HTTP_CONFLICT);
            }
        }

        $comment = $nickname ? '; ' . $this->sanitizeComment($nickname) : '';
        $line = $this->formatAdminLine($auth, $password, $accessFlags, $accountFlags, $comment, !$enabled);

        $parsed['admins'][$id] = [
            'id' => $id,
            'auth' => $auth,
            'password' => $password,
            'access_flags' => $accessFlags,
            'account_flags' => $accountFlags,
            'auth_type' => $authType,
            'nickname' => $nickname,
            'enabled' => $enabled,
            'comment' => $comment ? ltrim($comment, '; ') : null,
            'line' => $line,
        ];

        $this->writeUsersIni($server, $paths['users_ini'], $parsed);
        $commandSent = $reload ? $this->trySendCommand($server, 'amx_reloadadmins') : false;

        return [
            'admin' => $parsed['admins'][$id],
            'command_sent' => $commandSent,
        ];
    }

    public function deleteAdmin(Server $server, int $id, bool $reload = true): bool
    {
        $paths = $this->resolvePaths($server);
        $content = $this->readFile($server, $paths['users_ini']);
        $parsed = $this->parseUsersIni($content);

        if (!isset($parsed['admins'][$id])) {
            throw new AmxxException('Admin não encontrado.', Response::HTTP_NOT_FOUND);
        }

        unset($parsed['admins'][$id]);
        $parsed['admins'] = array_values($parsed['admins']);

        foreach ($parsed['admins'] as $index => &$admin) {
            $admin['id'] = $index;
        }
        unset($admin);

        $this->writeUsersIni($server, $paths['users_ini'], $parsed);

        return $reload ? $this->trySendCommand($server, 'amx_reloadadmins') : false;
    }

    public function listBans(Server $server): array
    {
        $paths = $this->resolvePaths($server);
        $bans = [];
        $gameListing = $this->getDirectoryListing($server, $paths['game_dir']);

        if ($gameListing !== null && $this->fileExistsInDirectoryListing($gameListing, 'banned.cfg')) {
            $content = $this->readFile($server, $paths['banned_cfg']);
            foreach ($this->parseBannedCfg($content) as $ban) {
                $bans[] = $ban;
            }
        }

        if ($gameListing !== null && $this->fileExistsInDirectoryListing($gameListing, 'listip.cfg')) {
            $content = $this->readFile($server, $paths['listip_cfg']);
            foreach ($this->parseListIpCfg($content) as $ban) {
                $bans[] = $ban;
            }
        }

        return $bans;
    }

    public function createBan(
        Server $server,
        string $type,
        string $identifier,
        int $minutes = 0,
        ?string $reason = null,
        bool $applyLive = true,
    ): array {
        $identifier = trim($identifier);
        $reason = $reason ? $this->sanitizeComment($reason) : null;

        if ($type === 'steamid') {
            if (!preg_match('/^STEAM_[0-5]:[01]:\d+$/i', $identifier)) {
                throw new AmxxException('SteamID inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $paths = $this->resolvePaths($server);
            $content = $this->fileExists($server, $paths['banned_cfg'])
                ? $this->readFile($server, $paths['banned_cfg'])
                : '';
            $line = 'banid ' . max(0, $minutes) . ' ' . $identifier . ($reason ? ' // ' . $reason : '');
            $content = rtrim($content) . ($content === '' ? '' : "\n") . $line . "\n";
            $this->writeFile($server, $paths['banned_cfg'], $content);

            $command = 'amx_ban "' . $identifier . '" ' . max(0, $minutes) . ($reason ? ' "' . $reason . '"' : '');
            $commandSent = $applyLive && $this->trySendCommand($server, $command);

            return [
                'ban' => [
                    'id' => 'steam:' . strtolower($identifier),
                    'type' => 'steamid',
                    'identifier' => $identifier,
                    'minutes' => max(0, $minutes),
                    'reason' => $reason,
                    'permanent' => $minutes === 0,
                ],
                'command_sent' => $commandSent,
            ];
        }

        if ($type === 'ip') {
            if (!filter_var($identifier, FILTER_VALIDATE_IP)) {
                throw new AmxxException('Endereço IP inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $paths = $this->resolvePaths($server);
            $content = $this->fileExists($server, $paths['listip_cfg'])
                ? $this->readFile($server, $paths['listip_cfg'])
                : '';
            $line = 'addip ' . $identifier . ($reason ? ' // ' . $reason : '');
            $content = rtrim($content) . ($content === '' ? '' : "\n") . $line . "\n";
            $this->writeFile($server, $paths['listip_cfg'], $content);

            $command = 'amx_banip "' . $identifier . '" ' . max(0, $minutes) . ($reason ? ' "' . $reason . '"' : '');
            $commandSent = $applyLive && $this->trySendCommand($server, $command);

            return [
                'ban' => [
                    'id' => 'ip:' . $identifier,
                    'type' => 'ip',
                    'identifier' => $identifier,
                    'minutes' => max(0, $minutes),
                    'reason' => $reason,
                    'permanent' => $minutes === 0,
                ],
                'command_sent' => $commandSent,
            ];
        }

        throw new AmxxException('Tipo de ban inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function deleteBan(Server $server, string $banId, bool $applyLive = true): bool
    {
        if (str_starts_with($banId, 'steam:')) {
            $steamId = substr($banId, 6);
            $paths = $this->resolvePaths($server);
            $content = $this->readFile($server, $paths['banned_cfg']);
            $updated = $this->removeBannedCfgEntry($content, $steamId);
            $this->writeFile($server, $paths['banned_cfg'], $updated);

            return $applyLive && $this->trySendCommand($server, 'amx_unban "' . $steamId . '"');
        }

        if (str_starts_with($banId, 'ip:')) {
            $ip = substr($banId, 3);
            $paths = $this->resolvePaths($server);
            $content = $this->readFile($server, $paths['listip_cfg']);
            $updated = $this->removeListIpEntry($content, $ip);
            $this->writeFile($server, $paths['listip_cfg'], $updated);

            return $applyLive && $this->trySendCommand($server, 'removeip ' . $ip);
        }

        throw new AmxxException('Ban não encontrado.', Response::HTTP_NOT_FOUND);
    }

    private function resolvePaths(Server $server): array
    {
        if (isset($this->resolvedPathsCache[$server->id])) {
            return $this->resolvedPathsCache[$server->id];
        }

        $gameDir = $this->detectGameDirectory($server);

        return $this->resolvedPathsCache[$server->id] = [
            'game_dir' => $gameDir,
            'users_ini' => $gameDir . '/addons/amxmodx/configs/users.ini',
            'banned_cfg' => $gameDir . '/banned.cfg',
            'listip_cfg' => $gameDir . '/listip.cfg',
        ];
    }

    private function detectGameDirectory(Server $server): string
    {
        if (isset($this->gameDirectoryCache[$server->id])) {
            return $this->gameDirectoryCache[$server->id];
        }

        $configured = $this->resolveGameDirectoryFromVariables($server);
        $candidates = array_values(array_unique(array_filter([$configured, 'cstrike', 'valve', 'czero'])));

        foreach ($candidates as $candidate) {
            $configsListing = $this->getDirectoryListing($server, $candidate . '/addons/amxmodx/configs');
            if ($configsListing !== null && $this->fileExistsInDirectoryListing($configsListing, 'users.ini')) {
                return $this->gameDirectoryCache[$server->id] = $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if ($this->directoryExists($server, $candidate . '/addons/amxmodx')) {
                return $this->gameDirectoryCache[$server->id] = $candidate;
            }
        }

        return $this->gameDirectoryCache[$server->id] = $configured;
    }

    private function resolveGameDirectoryFromVariables(Server $server): string
    {
        if (isset($this->configuredGameDirectoryCache[$server->id])) {
            return $this->configuredGameDirectoryCache[$server->id];
        }
        $eggVariables = EggVariable::query()
            ->where('egg_id', $server->egg_id)
            ->get(['id', 'env_variable', 'default_value']);

        $overrides = ServerVariable::query()
            ->where('server_id', $server->id)
            ->whereIn('variable_id', $eggVariables->pluck('id'))
            ->pluck('variable_value', 'variable_id');

        $variables = [];
        foreach ($eggVariables as $variable) {
            $override = $overrides->get($variable->id);
            $value = (is_null($override) || $override === '')
                ? $variable->default_value
                : $override;

            $variables[strtoupper(trim((string) $variable->env_variable))] = is_string($value) ? trim($value) : $value;
        }

        $game = strtolower((string) ($variables['HLDS_GAME'] ?? $variables['SRCDS_GAME'] ?? 'cstrike'));
        if ($game === '') {
            $game = 'cstrike';
        }

        return $this->configuredGameDirectoryCache[$server->id] = $game;
    }

    private function readFile(Server $server, string $path): string
    {
        try {
            return $this->fileRepository->setServer($server)->getContent($path);
        } catch (DaemonConnectionException $exception) {
            $previous = $exception->getPrevious();
            if ($previous instanceof ClientException && $previous->getResponse()?->getStatusCode() === Response::HTTP_NOT_FOUND) {
                return '';
            }

            throw $this->buildFileAccessException($server, $path, $exception);
        }
    }

    private function buildFileAccessException(Server $server, string $path, DaemonConnectionException $exception): AmxxException
    {
        if (!$this->wingsReachable($server)) {
            return new AmxxException(
                'Não foi possível comunicar com o node Wings deste servidor. Verifique se o node está online e se o gestor de ficheiros abre normalmente.',
                Response::HTTP_BAD_GATEWAY,
                $exception
            );
        }

        return new AmxxException(
            'Não foi possível ler o ficheiro "' . $path . '". Confirme que o AMXX está instalado e que a variável HLDS_GAME do servidor está correta.',
            Response::HTTP_BAD_GATEWAY,
            $exception
        );
    }

    private function wingsReachable(Server $server): bool
    {
        if (array_key_exists($server->id, $this->wingsReachableCache)) {
            return $this->wingsReachableCache[$server->id];
        }

        try {
            $this->fileRepository->setServer($server)->getDirectory('/');

            return $this->wingsReachableCache[$server->id] = true;
        } catch (DaemonConnectionException) {
            return $this->wingsReachableCache[$server->id] = false;
        }
    }

    /**
     * @return array|null Null when the directory does not exist.
     */
    private function getDirectoryListing(Server $server, string $path): ?array
    {
        $path = ltrim($path, '/');

        if (array_key_exists($path, $this->directoryListingCache[$server->id] ?? [])) {
            return $this->directoryListingCache[$server->id][$path];
        }

        try {
            $listing = $this->fileRepository->setServer($server)->getDirectory($path);
        } catch (DaemonConnectionException) {
            $this->directoryListingCache[$server->id][$path] = null;

            return null;
        }

        $this->directoryListingCache[$server->id][$path] = $listing;

        return $listing;
    }

    private function directoryExists(Server $server, string $path): bool
    {
        return $this->getDirectoryListing($server, $path) !== null;
    }

    private function fileExists(Server $server, string $path): bool
    {
        $path = ltrim($path, '/');
        $directory = dirname($path);
        $fileName = basename($path);

        if ($fileName === '' || $directory === '.') {
            return false;
        }

        $listing = $this->getDirectoryListing($server, $directory);

        return $listing !== null && $this->fileExistsInDirectoryListing($listing, $fileName);
    }

    private function fileExistsInDirectoryListing(array $listing, string $fileName): bool
    {
        foreach ($listing as $item) {
            if (($item['name'] ?? null) === $fileName && ($item['file'] ?? true)) {
                return true;
            }
        }

        return false;
    }

    private function writeFile(Server $server, string $path, string $content): void
    {
        try {
            $this->fileRepository->setServer($server)->putContent($path, $content);
        } catch (DaemonConnectionException $exception) {
            if (!$this->wingsReachable($server)) {
                throw new AmxxException(
                    'Não foi possível comunicar com o node Wings deste servidor. Verifique se o node está online e se o gestor de ficheiros abre normalmente.',
                    Response::HTTP_BAD_GATEWAY,
                    $exception
                );
            }

            throw new AmxxException(
                'Não foi possível escrever o ficheiro "' . $path . '".',
                Response::HTTP_BAD_GATEWAY,
                $exception
            );
        }
    }


    private function trySendCommand(Server $server, string $command): bool
    {
        try {
            $this->commandRepository->setServer($server)->send($command);

            return true;
        } catch (DaemonConnectionException) {
            return false;
        }
    }

    private function parseUsersIni(string $content): array
    {
        $header = [];
        $admins = [];
        $id = 0;

        foreach (preg_split('/\r\n|\n|\r/', $content) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || !preg_match('/^;?\s*"/', $trimmed)) {
                $header[] = $line;
                continue;
            }

            $enabled = !str_starts_with(ltrim($line), ';');
            $workingLine = $enabled ? $trimmed : ltrim(ltrim($line), ';');

            if (!preg_match('/^"([^"]*)"\s+"([^"]*)"\s+"([^"]*)"\s+"([^"]*)"(?:\s*;(.*))?$/', $workingLine, $matches)) {
                $header[] = $line;
                continue;
            }

            $auth = $matches[1];
            $password = $matches[2];
            $accessFlags = $matches[3];
            $accountFlags = $matches[4];
            $comment = isset($matches[5]) ? trim($matches[5]) : null;

            $admins[] = [
                'id' => $id++,
                'auth' => $auth,
                'password' => $password,
                'access_flags' => $accessFlags,
                'account_flags' => $accountFlags,
                'auth_type' => $this->detectAuthType($accountFlags),
                'nickname' => $comment,
                'enabled' => $enabled,
                'comment' => $comment,
                'line' => $this->formatAdminLine($auth, $password, $accessFlags, $accountFlags, $comment ? '; ' . $comment : '', !$enabled),
            ];
        }

        return [
            'header' => $header,
            'admins' => $admins,
        ];
    }

    private function writeUsersIni(Server $server, string $path, array $parsed): void
    {
        $lines = $parsed['header'];

        while (!empty($lines) && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        foreach ($parsed['admins'] as $admin) {
            $lines[] = $admin['line'];
        }

        $content = rtrim(implode("\n", $lines)) . "\n";
        $this->writeFile($server, $path, $content);
    }

    private function formatAdminLine(
        string $auth,
        string $password,
        string $accessFlags,
        string $accountFlags,
        string $comment = '',
        bool $disabled = false,
    ): string {
        $line = sprintf('"%s" "%s" "%s" "%s"', $auth, $password, $accessFlags, $accountFlags);

        if ($comment !== '') {
            $line .= ' ' . $comment;
        }

        return $disabled ? ';' . $line : $line;
    }

    private function detectAuthType(string $accountFlags): string
    {
        if (str_contains($accountFlags, 'c')) {
            return 'steamid';
        }

        if (str_contains($accountFlags, 'd')) {
            return 'ip';
        }

        return 'nickname';
    }

    private function resolveAccountFlags(string $authType, string $password): string
    {
        return match ($authType) {
            'steamid' => 'ce',
            'ip' => 'de',
            default => $password === '' ? 'e' : 'a',
        };
    }

    private function normalizeAccessFlags(string $flags): string
    {
        $flags = strtolower(preg_replace('/[^a-z]/', '', $flags) ?? '');
        $chars = array_unique(str_split($flags));
        sort($chars);

        $normalized = implode('', array_filter($chars, fn ($char) => $char !== 'z'));

        if ($normalized === '') {
            throw new AmxxException('Selecione pelo menos uma permissão de admin.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $normalized;
    }

    private function sanitizeComment(string $value): string
    {
        return trim(preg_replace('/[\r\n"]/', '', $value) ?? '');
    }

    private function parseBannedCfg(string $content): array
    {
        $bans = [];

        foreach (preg_split('/\r\n|\n|\r/', $content) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }

            if (!preg_match('/^banid\s+(\d+)\s+(\S+)(?:\s*\/\/\s*(.*))?$/i', $trimmed, $matches)) {
                continue;
            }

            $minutes = (int) $matches[1];
            $identifier = $matches[2];
            $reason = isset($matches[3]) ? trim($matches[3]) : null;

            $bans[] = [
                'id' => 'steam:' . strtolower($identifier),
                'type' => 'steamid',
                'identifier' => $identifier,
                'minutes' => $minutes,
                'reason' => $reason,
                'permanent' => $minutes === 0,
            ];
        }

        return $bans;
    }

    private function parseListIpCfg(string $content): array
    {
        $bans = [];

        foreach (preg_split('/\r\n|\n|\r/', $content) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//')) {
                continue;
            }

            if (!preg_match('/^addip\s+([\d.]+)(?:\s*\/\/\s*(.*))?$/i', $trimmed, $matches)) {
                continue;
            }

            $bans[] = [
                'id' => 'ip:' . $matches[1],
                'type' => 'ip',
                'identifier' => $matches[1],
                'minutes' => 0,
                'reason' => isset($matches[2]) ? trim($matches[2]) : null,
                'permanent' => true,
            ];
        }

        return $bans;
    }

    private function removeBannedCfgEntry(string $content, string $steamId): string
    {
        $lines = [];
        $found = false;

        foreach (preg_split('/\r\n|\n|\r/', $content) as $line) {
            if (preg_match('/^banid\s+\d+\s+(\S+)/i', trim($line), $matches)
                && strcasecmp($matches[1], $steamId) === 0) {
                $found = true;
                continue;
            }

            $lines[] = $line;
        }

        if (!$found) {
            throw new AmxxException('Ban não encontrado.', Response::HTTP_NOT_FOUND);
        }

        return rtrim(implode("\n", $lines)) . (empty($lines) ? '' : "\n");
    }

    private function removeListIpEntry(string $content, string $ip): string
    {
        $lines = [];
        $found = false;

        foreach (preg_split('/\r\n|\n|\r/', $content) as $line) {
            if (preg_match('/^addip\s+([\d.]+)/i', trim($line), $matches)
                && $matches[1] === $ip) {
                $found = true;
                continue;
            }

            $lines[] = $line;
        }

        if (!$found) {
            throw new AmxxException('Ban não encontrado.', Response::HTTP_NOT_FOUND);
        }

        return rtrim(implode("\n", $lines)) . (empty($lines) ? '' : "\n");
    }
}
