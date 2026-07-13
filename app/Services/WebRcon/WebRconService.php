<?php

namespace Pterodactyl\Services\WebRcon;

use Illuminate\Http\Response;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Support\GameDigTypeResolver;
use Pterodactyl\Exceptions\Service\WebRcon\WebRconException;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;
use Pterodactyl\Repositories\Wings\DaemonConsoleCommandRepository;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;

class WebRconService
{
    /** @var array<string, string> */
    private const DEFAULT_GAME_DIRS = [
        'cod4' => 'main',
        'cod-waw' => 'main',
        'codmw3' => 'main',
        'ql' => 'baseq3',
        'quake-live' => 'baseq3',
    ];

    /** @var array<int, bool|null> */
    private array $wingsReachableCache = [];

    /** @var array<int, array<string, array|null>> */
    private array $directoryListingCache = [];

    /** @var array<int, string> */
    private array $gameDirectoryCache = [];

    /** @var array<int, string> */
    private array $configuredGameDirectoryCache = [];

    public function __construct(
        private DaemonFileRepository $fileRepository,
        private DaemonCommandRepository $commandRepository,
        private DaemonConsoleCommandRepository $consoleCommandRepository,
        private IdTech3ConsoleParser $parser,
    ) {
    }

    public function overview(Server $server): array
    {
        $gameDir = $this->detectGameDirectory($server);
        $wingsReachable = $this->wingsReachable($server);
        $status = $wingsReachable ? $this->fetchStatus($server) : [
            'hostname' => null,
            'map' => null,
            'max_clients' => null,
            'players' => [],
        ];

        return [
            'game_directory' => $gameDir,
            'wings_reachable' => $wingsReachable,
            'hostname' => $status['hostname'],
            'map' => $status['map'],
            'max_clients' => $status['max_clients'],
            'player_count' => count($status['players']),
            'gamedig' => GameDigTypeResolver::resolve($server->egg?->gamedig, $server->egg?->name),
        ];
    }

    /**
     * @return array{
     *     players: array<int, array<string, mixed>>,
     *     hostname: ?string,
     *     map: ?string,
     *     max_clients: ?int,
     *     queried_at?: string
     * }
     */
    public function listPlayers(Server $server): array
    {
        $status = $this->fetchStatus($server);

        return [
            'players' => $status['players'],
            'hostname' => $status['hostname'],
            'map' => $status['map'],
            'max_clients' => $status['max_clients'],
        ];
    }

    public function kickPlayer(Server $server, int $clientnum, ?string $reason = null): array
    {
        if ($clientnum < 0) {
            throw new WebRconException('O ID do jogador é inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $command = 'kick ' . $clientnum;
        if ($reason !== null && trim($reason) !== '') {
            $command .= ' "' . $this->sanitizeText($reason) . '"';
        }

        $this->sendRequiredCommand($server, $command);

        return [
            'clientnum' => $clientnum,
            'command_sent' => true,
        ];
    }

    public function banPlayer(Server $server, int $clientnum, int $minutes = 0, ?string $guid = null): array
    {
        if ($clientnum < 0) {
            throw new WebRconException('O ID do jogador é inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $minutes = max(0, $minutes);
        $guid = $guid ? strtolower(trim($guid)) : null;

        if ($minutes === 0) {
            $command = 'banClient ' . $clientnum;
        } elseif ($guid && preg_match('/^[0-9a-f]+$/', $guid)) {
            $command = 'banUser ' . $guid . ' ' . $minutes;
        } else {
            $command = 'tempBanClient ' . $clientnum;
        }

        $this->sendRequiredCommand($server, $command);

        return [
            'clientnum' => $clientnum,
            'minutes' => $minutes,
            'command_sent' => true,
        ];
    }

    public function listMaps(Server $server): array
    {
        $gameDir = $this->detectGameDirectory($server);
        $listing = $this->getDirectoryListing($server, $gameDir . '/maps');
        $maps = [];

        if ($listing !== null) {
            foreach ($listing as $item) {
                $name = $item['name'] ?? null;
                if (!$name || !($item['file'] ?? false)) {
                    continue;
                }

                if (!str_ends_with(strtolower($name), '.d3dbsp') && !str_ends_with(strtolower($name), '.bsp')) {
                    continue;
                }

                $mapName = pathinfo($name, PATHINFO_FILENAME);
                if ($mapName === '') {
                    continue;
                }

                $maps[] = [
                    'name' => $mapName,
                    'file' => $name,
                ];
            }
        }

        usort($maps, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $maps;
    }

    public function changeMap(Server $server, string $map): array
    {
        $map = strtolower(trim($map));
        $map = preg_replace('/[^a-z0-9_-]/', '', $map) ?? '';

        if ($map === '') {
            throw new WebRconException('Nome de mapa inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $available = array_column($this->listMaps($server), 'name');
        if (!empty($available) && !in_array($map, $available, true)) {
            throw new WebRconException('Mapa não encontrado no servidor.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->sendRequiredCommand($server, 'map ' . $map);

        return [
            'map' => $map,
            'command_sent' => true,
        ];
    }

    public function sendSay(Server $server, string $message): array
    {
        $message = $this->sanitizeText($message);
        if ($message === '') {
            throw new WebRconException('A mensagem é obrigatória.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->sendRequiredCommand($server, 'say "' . $message . '"');

        return [
            'message' => $message,
            'command_sent' => true,
        ];
    }

    public function sendTell(Server $server, int $clientnum, string $message): array
    {
        if ($clientnum < 0) {
            throw new WebRconException('O ID do jogador é inválido.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $message = $this->sanitizeText($message);
        if ($message === '') {
            throw new WebRconException('A mensagem é obrigatória.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->sendRequiredCommand($server, 'tell ' . $clientnum . ' "' . $message . '"');

        return [
            'clientnum' => $clientnum,
            'message' => $message,
            'command_sent' => true,
        ];
    }

    /**
     * @return array{
     *     hostname: ?string,
     *     map: ?string,
     *     max_clients: ?int,
     *     players: array<int, array<string, mixed>>
     * }
     */
    private function fetchStatus(Server $server): array
    {
        try {
            $result = $this->executeConsoleQuery($server, 'status');
        } catch (WebRconException $exception) {
            throw $exception;
        }

        return $this->parser->parseStatus($result['lines'] ?? []);
    }

    private function detectGameDirectory(Server $server): string
    {
        if (isset($this->gameDirectoryCache[$server->id])) {
            return $this->gameDirectoryCache[$server->id];
        }

        $configured = $this->resolveGameDirectoryFromVariables($server);
        $gamedig = GameDigTypeResolver::resolve($server->egg?->gamedig, $server->egg?->name) ?? '';
        $default = self::DEFAULT_GAME_DIRS[$gamedig] ?? 'main';

        $candidates = array_values(array_unique(array_filter([$configured, $default, 'main', 'mp'])));

        foreach ($candidates as $candidate) {
            if ($this->directoryExists($server, $candidate . '/maps')) {
                return $this->gameDirectoryCache[$server->id] = $candidate;
            }
        }

        return $this->gameDirectoryCache[$server->id] = $configured ?: $default;
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

        $game = strtolower((string) (
            $variables['FS_GAME']
            ?? $variables['GAME_DIR']
            ?? $variables['COD_GAME']
            ?? $variables['HLDS_GAME']
            ?? ''
        ));

        return $this->configuredGameDirectoryCache[$server->id] = $game;
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

        return $this->directoryListingCache[$server->id][$path] = $listing;
    }

    private function directoryExists(Server $server, string $path): bool
    {
        return $this->getDirectoryListing($server, $path) !== null;
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

    private function sendRequiredCommand(Server $server, string $command): void
    {
        if (!$this->trySendCommand($server, $command)) {
            throw new WebRconException(
                'Não foi possível enviar o comando. Verifique se o servidor está online.',
                Response::HTTP_BAD_GATEWAY
            );
        }
    }

    /**
     * @return array{command: string, lines: string[], queried_at?: string}
     */
    private function executeConsoleQuery(Server $server, string $command): array
    {
        try {
            return $this->consoleCommandRepository->setServer($server)->execute($command);
        } catch (DaemonConnectionException $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof ConnectException) {
                throw new WebRconException(
                    'Não foi possível comunicar com o node Wings deste servidor.',
                    Response::HTTP_BAD_GATEWAY,
                    $exception
                );
            }

            if ($previous instanceof ClientException) {
                $statusCode = $previous->getResponse()?->getStatusCode();

                if ($statusCode === Response::HTTP_BAD_GATEWAY) {
                    throw new WebRconException(
                        'O servidor precisa estar online para consultar a consola.',
                        Response::HTTP_BAD_GATEWAY,
                        $exception
                    );
                }

                if ($statusCode === Response::HTTP_GATEWAY_TIMEOUT || $statusCode === Response::HTTP_REQUEST_TIMEOUT) {
                    throw new WebRconException(
                        'A consulta à consola expirou. Tente novamente em instantes.',
                        Response::HTTP_GATEWAY_TIMEOUT,
                        $exception
                    );
                }
            }

            throw new WebRconException(
                'Não foi possível consultar a consola do servidor.',
                Response::HTTP_BAD_GATEWAY,
                $exception
            );
        }
    }

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/[\r\n"]/', '', $value) ?? '');
    }
}
