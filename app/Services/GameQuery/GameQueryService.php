<?php

namespace Pterodactyl\Services\GameQuery;

use GameQ\GameQ;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\GameDigTypeResolver;
use Pterodactyl\Support\ServerType;
use Pterodactyl\Exceptions\Service\GameQuery\GameQueryException;

class GameQueryService
{
    private const QUERY_TIMEOUT_SECONDS = 5;

    public function query(Server $server): array
    {
        if (ServerType::isTs3($server)) {
            throw new GameQueryException(
                'Este servidor TeamSpeak 3 utiliza a API dedicada de query TS3.',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $server->loadMissing(['egg', 'allocation', 'node', 'variables']);

        $gameType = GameDigTypeResolver::resolve(
            $server->egg->gamedig ?? null,
            $server->egg->name ?? null
        );

        if ($gameType === null) {
            throw new GameQueryException(
                'Configure o campo GameDig do egg com um tipo suportado pelo GameQ (ex.: cs16, cod4, samp).',
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

        $target = $this->resolveQueryTarget($server, $allocation);

        $gameQ = new GameQ();
        $gameQ->setOption('timeout', self::QUERY_TIMEOUT_SECONDS);

        $serverConfig = [
            'type' => $gameType,
            'host' => sprintf('%s:%d', $target['host'], $target['port']),
        ];

        if ($target['query_port'] !== $target['port']) {
            $serverConfig['options'] = [
                'query_port' => $target['query_port'],
            ];
        }

        $gameQ->addServer($serverConfig);

        $results = $gameQ->process();
        $result = reset($results) ?: [];

        return $this->formatResponse($gameType, $target, $result);
    }

    /**
     * @return array{host: string, port: int, query_port: int}
     */
    private function resolveQueryTarget(Server $server, $allocation): array
    {
        $port = (int) $allocation->port;
        $host = $allocation->alias;

        if (!$this->isPublicIp($allocation->ip) && !empty($server->node?->fqdn)) {
            $host = $server->node->fqdn;
        }

        $queryPort = $this->resolveQueryPort($server, $port);

        return [
            'host' => $host,
            'port' => $port,
            'query_port' => $queryPort,
        ];
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

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function formatResponse(string $gameType, array $target, array $result): array
    {
        $online = (bool) ($result['gq_online'] ?? false);
        $players = [];

        foreach ($result['players'] ?? [] as $player) {
            if (!is_array($player)) {
                continue;
            }

            $players[] = array_filter([
                'name' => $player['gq_name'] ?? $player['name'] ?? null,
                'score' => isset($player['gq_score']) ? (int) $player['gq_score'] : (isset($player['score']) ? (int) $player['score'] : null),
                'time' => isset($player['gq_time']) ? (int) $player['gq_time'] : (isset($player['time']) ? (int) $player['time'] : null),
                'ping' => isset($player['gq_ping']) ? (int) $player['gq_ping'] : (isset($player['ping']) ? (int) $player['ping'] : null),
            ], static fn ($value) => $value !== null);
        }

        return [
            'online' => $online,
            'type' => $gameType,
            'address' => $target['host'],
            'port' => $target['port'],
            'query_port' => $target['query_port'],
            'hostname' => $result['gq_hostname'] ?? $result['hostname'] ?? null,
            'map' => $result['gq_mapname'] ?? $result['map'] ?? null,
            'game' => $result['gq_gametype'] ?? $result['game'] ?? null,
            'players' => (int) ($result['gq_numplayers'] ?? $result['numplayers'] ?? count($players)),
            'max_players' => (int) ($result['gq_maxplayers'] ?? $result['maxplayers'] ?? 0),
            'password_protected' => (bool) ($result['gq_password'] ?? $result['password'] ?? false),
            'version' => $result['gq_version'] ?? $result['version'] ?? null,
            'player_list' => $players,
            'queried_at' => now()->toAtomString(),
        ];
    }
}
