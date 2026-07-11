<?php

namespace Pterodactyl\Services\GameQuery;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\GameDigTypeResolver;
use Pterodactyl\Support\ServerType;
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
        $queryPort = $this->resolveQueryPort($server, $port);

        try {
            $result = $this->daemonGameQueryRepository->setServer($server)->query($gameType, $queryPort);
        } catch (DaemonConnectionException $exception) {
            throw new GameQueryException(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception
            );
        }

        return $this->formatResponse($gameType, $allocation->ip, $port, $queryPort, $result);
    }

    private function resolveQueryPort(Server $server, int $defaultPort): int
    {
        foreach ($server->variables as $variable) {
            if (!in_array($variable->env_variable, ['QUERY_PORT', 'SERVER_QUERY_PORT'], true)) {
                continue;
            }

            $value = trim((string) ($variable->server_value ?? ''));
            if ($value !== '' && ctype_digit($value)) {
                return (int) $value;
            }
        }

        return $defaultPort;
    }

    private function formatResponse(string $gameType, string $address, int $port, int $queryPort, array $result): array
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

        return [
            'online' => (bool) ($result['online'] ?? false),
            'type' => $gameType,
            'address' => $result['address'] ?? $address,
            'port' => (int) ($result['port'] ?? $port),
            'query_port' => (int) ($result['query_port'] ?? $queryPort),
            'hostname' => $result['hostname'] ?? null,
            'map' => $result['map'] ?? null,
            'game' => $result['game'] ?? null,
            'players' => max((int) ($result['players'] ?? 0), count($players)),
            'max_players' => (int) ($result['max_players'] ?? 0),
            'password_protected' => (bool) ($result['password_protected'] ?? false),
            'version' => $result['version'] ?? null,
            'player_list' => $players,
            'queried_at' => $result['queried_at'] ?? now()->toAtomString(),
        ];
    }
}
