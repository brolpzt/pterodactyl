<?php

namespace Pterodactyl\Services\Ts3;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Exceptions\Service\Ts3\Ts3QueryException;

class Ts3QueryService
{
    private const DEFAULT_HTTP_TIMEOUT_SECONDS = 8;
    private const DEFAULT_QUERY_HTTP_PORT = 10080;

    public function overview(Server $server): array
    {
        $config = $this->resolveConfiguration($server);

        $serverInfo = $this->executeHttpCommand($config, 'serverinfo');
        $version = $this->executeHttpCommand($config, 'version', [], true);
        $connectionInfo = $this->executeHttpCommand($config, 'serverrequestconnectioninfo');

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
    }

    public function listBans(Server $server): array
    {
        $config = $this->resolveConfiguration($server);
        $body = $this->executeHttpCommand($config, 'banlist');

        return is_array($body) && array_is_list($body) ? $body : [$body];
    }

    public function createBan(Server $server, string $ip, int $time = 0, ?string $reason = null): void
    {
        $config = $this->resolveConfiguration($server);
        $params = ['ip' => $ip, 'time' => $time];
        if (!empty($reason)) {
            $params['banreason'] = $reason;
        }

        $this->executeHttpCommand($config, 'banadd', $params);
    }

    public function deleteBan(Server $server, int $banId): void
    {
        $config = $this->resolveConfiguration($server);
        $this->executeHttpCommand($config, 'bandel', ['banid' => $banId]);
    }

    public function listTokens(Server $server): array
    {
        $config = $this->resolveConfiguration($server);
        $body = $this->executeHttpCommand($config, 'tokenlist');

        return is_array($body) && array_is_list($body) ? $body : [$body];
    }

    public function createToken(Server $server, int $groupId = 6, string $description = ''): array
    {
        $config = $this->resolveConfiguration($server);
        $params = ['tokentype' => 0, 'tokenid1' => $groupId, 'tokenid2' => 0];
        if ($description !== '') {
            $params['tokendescription'] = $description;
        }

        return $this->executeHttpCommand($config, 'tokenadd', $params);
    }

    public function deleteToken(Server $server, string $token): void
    {
        $config = $this->resolveConfiguration($server);
        $this->executeHttpCommand($config, 'tokendelete', ['token' => $token]);
    }

    public function logs(Server $server, int $lines = 100): array
    {
        $normalized = max(1, min($lines, 500));
        $config = $this->resolveConfiguration($server);
        $body = $this->executeHttpCommand($config, 'logview', ['lines' => $normalized, 'reverse' => 1]);

        return is_array($body) && array_is_list($body) ? $body : [$body];
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
        $config = $this->resolveConfiguration($server);
        $result = $this->executeHttpCommand($config, 'serversnapshotcreate');
        $snapshot = $result['snapshot'] ?? null;

        if (!is_string($snapshot) || $snapshot === '') {
            throw new Ts3QueryException(
                'TS3 WebQuery did not return a snapshot payload.',
                Response::HTTP_BAD_GATEWAY
            );
        }

        return $snapshot;
    }

    public function restoreSnapshot(Server $server, string $snapshot): void
    {
        $config = $this->resolveConfiguration($server);
        $this->executeHttpCommand($config, 'serversnapshotdeploy', ['snapshot' => $snapshot]);
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

        $apiKey = (string) ($variables['TS3_QUERY_API_KEY'] ?? $variables['QUERY_API_KEY'] ?? $variables['TS3_QUERY_PASS'] ?? $variables['QUERY_PASS'] ?? '');
        if ($apiKey === '') {
            throw new Ts3QueryException(
                'TS3 HTTP Query API key is not configured for this server.',
                Response::HTTP_BAD_REQUEST
            );
        }

        return [
            'host' => (string) ($server->allocation?->ip ?? $server->node->fqdn),
            'scheme' => (string) ($variables['TS3_QUERY_HTTP_SCHEME'] ?? 'http'),
            'query_http_port' => (int) ($variables['TS3_QUERY_HTTP_PORT'] ?? $variables['QUERY_HTTP'] ?? self::DEFAULT_QUERY_HTTP_PORT),
            'api_key' => $apiKey,
            'virtual_server_port' => (int) ($variables['TS3_SERVER_PORT'] ?? $variables['SERVER_PORT'] ?? $server->allocation?->port ?? 9987),
            'server_id' => isset($variables['TS3_SERVER_ID']) ? (int) $variables['TS3_SERVER_ID'] : null,
            'html_viewer_url' => (string) ($variables['TS3_HTML_VIEWER_URL'] ?? ''),
        ];
    }

    private function executeHttpCommand(array $config, string $command, array $params = [], bool $instanceCommand = false): array
    {
        $path = ltrim($command, '/');
        if (!$instanceCommand) {
            if (!empty($config['server_id'])) {
                $path = sprintf('%d/%s', (int) $config['server_id'], $path);
            } else {
                $path = sprintf('byport/%d/%s', (int) $config['virtual_server_port'], $path);
            }
        }

        $url = sprintf(
            '%s://%s:%d/%s',
            $config['scheme'],
            $config['host'],
            $config['query_http_port'],
            $path
        );

        try {
            $response = Http::withHeaders([
                'x-api-key' => $config['api_key'],
                'Accept' => 'application/json',
            ])
                ->timeout(self::DEFAULT_HTTP_TIMEOUT_SECONDS)
                ->post($url . '?api-key=' . urlencode($config['api_key']), $params);
        } catch (\Throwable $exception) {
            throw new Ts3QueryException(
                'Unable to connect to TS3 HTTP Query endpoint.',
                Response::HTTP_GATEWAY_TIMEOUT,
                $exception
            );
        }

        if (!$response->ok()) {
            throw new Ts3QueryException(
                sprintf('TS3 HTTP Query request failed with status %d.', $response->status()),
                $response->status() >= 400 ? $response->status() : Response::HTTP_BAD_GATEWAY
            );
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new Ts3QueryException(
                'TS3 HTTP Query returned an invalid JSON response.',
                Response::HTTP_BAD_GATEWAY
            );
        }

        $statusCode = (int) ($data['status']['code'] ?? 0);
        if ($statusCode !== 0) {
            $message = (string) ($data['status']['message'] ?? 'unknown_error');
            throw new Ts3QueryException(
                "TS3 HTTP Query command failed: {$message} (code: {$statusCode}).",
                Response::HTTP_BAD_REQUEST
            );
        }

        $body = $data['body'] ?? [];
        if (!is_array($body)) {
            return [];
        }

        if (array_is_list($body)) {
            return $body;
        }

        return $body;
    }
}
