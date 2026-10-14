<?php

namespace Pterodactyl\Services\GameQuery;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\GameDigTypeResolver;
use Pterodactyl\Support\ServerType;
use Pterodactyl\Support\SlotMismatchResolver;
use Pterodactyl\Repositories\Wings\DaemonGameQueryRepository;
use Pterodactyl\Exceptions\Service\GameQuery\GameQueryException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class GameQueryService
{
    public function __construct(private DaemonGameQueryRepository $daemonGameQueryRepository)
    {
    }

    public function query(Server $server): array
    {
        if (ServerType::isTs3($server)) {
            throw new GameQueryException(
                'Este servidor TeamSpeak 3 utiliza a API dedicada de query TS3.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $server->loadMissing(['egg', 'allocation', 'variables']);

        $gameType = GameDigTypeResolver::resolve(
            $server->egg->gamedig ?? null,
            $server->egg->name ?? null
        );

        if ($gameType === null) {
            throw new GameQueryException(
                'Configure o campo GameDig do egg com um tipo suportado (ex.: cs16, cod4, samp).',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $allocation = $server->allocation;
        if ($allocation === null) {
            throw new GameQueryException(
                'Este servidor não possui alocação primária configurada.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $port = (int) $allocation->port;
        $queryPort = $this->resolveQueryPort($server, $gameType, $port);

        try {
            $result = $this->daemonGameQueryRepository->setServer($server)->query($gameType, $queryPort);
        } catch (DaemonConnectionException $exception) {
            throw new GameQueryException(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception
            );
        }

        // Prefer alias (public/GCore IP) over the private bind address.
        return $this->formatResponse($server, $gameType, $allocation->alias, $port, $queryPort, $result);
    }

    private function resolveQueryPort(Server $server, string $gameType, int $defaultPort): int
    {
        // 7DTD exposes A2S on the game port; SERVER_PORT+1 is not the query socket.
        if ($this->usesGamePortForQuery($gameType)) {
            return $defaultPort;
        }

        $server->loadMissing(['variables', 'allocations']);

        foreach ($server->variables as $variable) {
            if (!in_array($variable->env_variable, ['QUERY_PORT', 'SERVER_QUERY_PORT', 'VR_QUERY_PORT'], true)) {
                continue;
            }

            $value = trim((string) ($variable->server_value ?? ''));
            if ($value !== '' && ctype_digit($value)) {
                return (int) $value;
            }
        }

        // Fall back to the allocation bound to the query port slot.
        foreach ($server->allocations as $allocation) {
            $env = strtoupper(trim((string) ($allocation->port_env ?? '')));
            if (!in_array($env, ['QUERY_PORT', 'SERVER_QUERY_PORT', 'VR_QUERY_PORT'], true)) {
                continue;
            }

            $port = (int) $allocation->port;
            if ($port >= 1 && $port <= 65535) {
                return $port;
            }
        }

        return $defaultPort;
    }

    private function usesGamePortForQuery(string $gameType): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '', $gameType) ?? '');

        return in_array($normalized, ['7dtd', '7d2d', '7daystodie'], true);
    }

    private function formatResponse(Server $server, string $gameType, string $address, int $port, int $queryPort, array $result): array
    {
        $players = [];

        foreach ($result['player_list'] ?? [] as $player) {
            if (!is_array($player)) {
                continue;
            }

            $players[] = array_filter([
                'name' => $player['name'] ?? null,
                'score' => isset($player['score']) ? (int) $player['score'] : null,
                'time' => isset($player['time']) ? (int) $player['time'] : null,
                'ping' => isset($player['ping']) ? (int) $player['ping'] : null,
            ], static fn ($value) => $value !== null);
        }

        $online = (bool) ($result['online'] ?? false);
        $maxPlayers = (int) ($result['max_players'] ?? 0);

        return [
            'online' => $online,
            'type' => $gameType,
            // Always the allocation alias (public/GCore IP), never the Docker bridge.
            'address' => $address,
            'port' => (int) ($result['port'] ?? $port),
            'query_port' => (int) ($result['query_port'] ?? $queryPort),
            'hostname' => $result['hostname'] ?? null,
            'map' => $result['map'] ?? null,
            'game' => $result['game'] ?? null,
            'players' => max((int) ($result['players'] ?? 0), count($players)),
            'max_players' => $maxPlayers,
            'password_protected' => (bool) ($result['password_protected'] ?? false),
            'version' => $result['version'] ?? null,
            'player_list' => $players,
            'queried_at' => $result['queried_at'] ?? now()->toAtomString(),
            'slots' => SlotMismatchResolver::resolve($server, $online, $maxPlayers),
        ];
    }
}
