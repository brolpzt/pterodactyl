<?php

namespace Pterodactyl\Services\Ts3;

use Illuminate\Http\Response;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Exceptions\Service\Ts3\Ts3QueryException;

class Ts3QueryService
{
    private const DEFAULT_TIMEOUT_SECONDS = 5;
    private const DEFAULT_QUERY_PORT = 10011;

    /**
     * Executes a callback against an authenticated TS3 query session.
     */
    private function withQuery(Server $server, callable $callback): mixed
    {
        $config = $this->resolveConfiguration($server);

        $socket = @fsockopen(
            $config['host'],
            $config['query_port'],
            $errno,
            $errstr,
            self::DEFAULT_TIMEOUT_SECONDS
        );

        if (!$socket) {
            throw new Ts3QueryException(
                "Could not connect to TS3 Query at {$config['host']}:{$config['query_port']}.",
                Response::HTTP_BAD_GATEWAY
            );
        }

        stream_set_timeout($socket, self::DEFAULT_TIMEOUT_SECONDS);

        try {
            // Consume welcome lines before issuing commands.
            $this->readUntilErrorLine($socket);

            $this->sendCommand(
                $socket,
                'login client_login_name=' . $this->escape($config['user']) . ' client_login_password=' . $this->escape($config['password'])
            );

            if (!is_null($config['server_id'])) {
                $this->sendCommand($socket, 'use sid=' . $config['server_id']);
            } else {
                $this->sendCommand($socket, 'use port=' . $config['virtual_server_port']);
            }

            return $callback($socket);
        } finally {
            @fwrite($socket, "quit\n");
            @fclose($socket);
        }
    }

    public function overview(Server $server): array
    {
        return $this->withQuery($server, function ($socket) {
            $serverInfo = $this->sendCommand($socket, 'serverinfo')[0] ?? [];
            $version = $this->sendCommand($socket, 'version')[0] ?? [];
            $connectionInfo = $this->sendCommand($socket, 'serverrequestconnectioninfo')[0] ?? [];

            return [
                'status' => 'online',
                'version' => $version['version'] ?? null,
                'platform' => $version['platform'] ?? null,
                'build' => $version['build'] ?? null,
                'uptime_seconds' => isset($serverInfo['virtualserver_uptime']) ? (int) $serverInfo['virtualserver_uptime'] : null,
                'clients_online' => isset($serverInfo['virtualserver_clientsonline']) ? (int) $serverInfo['virtualserver_clientsonline'] : null,
                'channels_online' => isset($serverInfo['virtualserver_channelsonline']) ? (int) $serverInfo['virtualserver_channelsonline'] : null,
                'query_ip' => $connectionInfo['connection_ip'] ?? null,
                'query_port' => isset($connectionInfo['connection_port']) ? (int) $connectionInfo['connection_port'] : null,
                'server_name' => $serverInfo['virtualserver_name'] ?? null,
                'welcome_message' => $serverInfo['virtualserver_welcomemessage'] ?? null,
                'raw' => [
                    'serverinfo' => $serverInfo,
                    'version' => $version,
                    'connection_info' => $connectionInfo,
                ],
            ];
        });
    }

    public function listBans(Server $server): array
    {
        return $this->withQuery($server, fn ($socket) => $this->sendCommand($socket, 'banlist'));
    }

    public function createBan(Server $server, string $ip, int $time = 0, ?string $reason = null): void
    {
        $command = 'banadd ip=' . $this->escape($ip) . ' time=' . $time;
        if (!empty($reason)) {
            $command .= ' banreason=' . $this->escape($reason);
        }

        $this->withQuery($server, fn ($socket) => $this->sendCommand($socket, $command));
    }

    public function deleteBan(Server $server, int $banId): void
    {
        $this->withQuery($server, fn ($socket) => $this->sendCommand($socket, 'bandel banid=' . $banId));
    }

    public function listTokens(Server $server): array
    {
        return $this->withQuery($server, fn ($socket) => $this->sendCommand($socket, 'tokenlist'));
    }

    public function createToken(Server $server, int $groupId = 6, string $description = ''): array
    {
        return $this->withQuery($server, function ($socket) use ($groupId, $description) {
            $command = 'tokenadd tokentype=0 tokenid1=' . $groupId . ' tokenid2=0';
            if ($description !== '') {
                $command .= ' tokendescription=' . $this->escape($description);
            }

            return $this->sendCommand($socket, $command)[0] ?? [];
        });
    }

    public function deleteToken(Server $server, string $token): void
    {
        $this->withQuery($server, fn ($socket) => $this->sendCommand($socket, 'tokendelete token=' . $this->escape($token)));
    }

    public function logs(Server $server, int $lines = 100): array
    {
        $normalized = max(1, min($lines, 500));

        return $this->withQuery($server, fn ($socket) => $this->sendCommand($socket, "logview lines={$normalized} reverse=1"));
    }

    public function htmlViewer(Server $server): array
    {
        $overview = $this->overview($server);

        return [
            'url' => $this->resolveConfiguration($server)['html_viewer_url'],
            'server_name' => $overview['server_name'] ?? null,
            'welcome_message' => $overview['welcome_message'] ?? null,
        ];
    }

    public function createSnapshot(Server $server): string
    {
        return $this->withQuery($server, function ($socket) {
            $result = $this->sendCommand($socket, 'serversnapshotcreate')[0] ?? [];
            $snapshot = $result['snapshot'] ?? null;

            if (!is_string($snapshot) || $snapshot === '') {
                throw new Ts3QueryException(
                    'TS3 Query did not return a snapshot payload.',
                    Response::HTTP_BAD_GATEWAY
                );
            }

            return $snapshot;
        });
    }

    public function restoreSnapshot(Server $server, string $snapshot): void
    {
        $this->withQuery($server, fn ($socket) => $this->sendCommand($socket, 'serversnapshotdeploy snapshot=' . $this->escape($snapshot)));
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function sendCommand($socket, string $command): array
    {
        fwrite($socket, $command . "\n");

        return $this->readUntilErrorLine($socket);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readUntilErrorLine($socket): array
    {
        $rows = [];

        while (!feof($socket)) {
            $line = trim((string) fgets($socket));
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'error ')) {
                $error = $this->parseRow($line)[0] ?? [];
                $errorId = (int) ($error['id'] ?? -1);
                $errorMessage = $error['msg'] ?? 'unknown_error';

                if ($errorId !== 0) {
                    throw new Ts3QueryException(
                        "TS3 Query command failed: {$errorMessage} (code: {$errorId}).",
                        Response::HTTP_BAD_REQUEST
                    );
                }

                return $rows;
            }

            $rows = [...$rows, ...$this->parseRow($line)];
        }

        throw new Ts3QueryException(
            'TS3 Query connection closed unexpectedly.',
            Response::HTTP_BAD_GATEWAY
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseRow(string $line): array
    {
        $entries = explode('|', $line);
        $rows = [];

        foreach ($entries as $entry) {
            $pairs = preg_split('/\s+/', trim($entry)) ?: [];
            $row = [];
            foreach ($pairs as $pair) {
                if (!str_contains($pair, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $pair, 2);
                $row[$key] = $this->unescape($value);
            }

            if (!empty($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', ' ', '|', '/', "\n", "\r", "\t"],
            ['\\\\', '\s', '\p', '\/', '\n', '\r', '\t'],
            $value
        );
    }

    private function unescape(string $value): string
    {
        return str_replace(
            ['\s', '\p', '\/', '\n', '\r', '\t', '\\\\'],
            [' ', '|', '/', "\n", "\r", "\t", '\\'],
            $value
        );
    }

    private function resolveConfiguration(Server $server): array
    {
        $server->loadMissing(['allocation', 'node']);

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

        $user = (string) ($variables['TS3_QUERY_USER'] ?? $variables['QUERY_USER'] ?? '');
        $password = (string) ($variables['TS3_QUERY_PASS'] ?? $variables['QUERY_PASS'] ?? '');

        if ($user === '' || $password === '') {
            throw new Ts3QueryException(
                'TS3 Query credentials are not configured for this server.',
                Response::HTTP_BAD_REQUEST
            );
        }

        return [
            'host' => (string) ($variables['TS3_QUERY_HOST'] ?? $variables['QUERY_HOST'] ?? $server->allocation?->ip ?? $server->node->fqdn),
            // Backward compatible mapping for existing TS3 eggs:
            // - QUERY_PORT is commonly used as the startup variable name.
            'query_port' => (int) ($variables['TS3_QUERY_PORT'] ?? $variables['QUERY_PORT'] ?? self::DEFAULT_QUERY_PORT),
            // Prefer explicit TS3_SERVER_PORT, fallback to generic SERVER_PORT when present.
            'virtual_server_port' => (int) ($variables['TS3_SERVER_PORT'] ?? $variables['SERVER_PORT'] ?? $server->allocation?->port ?? 9987),
            'server_id' => isset($variables['TS3_SERVER_ID']) ? (int) $variables['TS3_SERVER_ID'] : null,
            'user' => $user,
            'password' => $password,
            'html_viewer_url' => (string) ($variables['TS3_HTML_VIEWER_URL'] ?? ''),
        ];
    }
}
