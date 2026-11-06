<?php

namespace Pterodactyl\Services\GameQuery;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\Service\GameQuery\GameQueryException;

/**
 * Plutonium (T4/T5/T6/IW5) servers answer no standard query reliably, but every
 * server heartbeats to plutonium.pw, whose public list has hostname, map,
 * players and bots. The whole list (~1300 servers) is fetched once and shared
 * by every panel server for a few seconds.
 */
class PlutoniumMasterList
{
    public const URL = 'https://plutonium.pw/api/servers';

    private const CACHE_KEY = 'game-query:plutonium:servers';

    private const CACHE_SECONDS = 30;

    /**
     * Returns the entry announced as one of $ips on $port, or null when the server
     * is not in the list (offline, or not announcing to the master).
     *
     * @param string[] $ips
     */
    public function find(array $ips, int $port): ?array
    {
        $index = $this->index();

        foreach (array_unique(array_filter($ips)) as $ip) {
            if (isset($index["{$ip}:{$port}"])) {
                return $index["{$ip}:{$port}"];
            }
        }

        return null;
    }

    /**
     * @return array<string, array> "ip:port" => server entry
     */
    private function index(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, function () {
            try {
                $response = Http::timeout(10)->acceptJson()->get(self::URL);
            } catch (\Throwable $exception) {
                throw new GameQueryException('Não foi possível consultar a lista do Plutonium: ' . $exception->getMessage(), Response::HTTP_BAD_GATEWAY, $exception);
            }

            $servers = $response->json();
            if (!$response->successful() || !is_array($servers)) {
                throw new GameQueryException("A lista do Plutonium respondeu HTTP {$response->status()}.", Response::HTTP_BAD_GATEWAY);
            }

            $index = [];
            foreach ($servers as $server) {
                if (is_array($server) && isset($server['ip'], $server['port'])) {
                    $index["{$server['ip']}:{$server['port']}"] = $server;
                }
            }

            return $index;
        });
    }
}
