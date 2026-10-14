<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonGameQueryRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonGameQueryRepository extends DaemonRepository
{
    /**
     * Queries the game server status via Wings on the node where the server runs.
     *
     * @throws DaemonConnectionException
     */
    public function query(string $gameType, int $queryPort): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        $this->server->loadMissing('allocation');
        $publicHost = trim((string) ($this->server->allocation?->alias ?? ''));

        try {
            $response = $this->getHttpClient()->get(
                "/api/servers/{$this->server->uuid}/query",
                [
                    'query' => array_filter([
                        'type' => $gameType,
                        'query_port' => $queryPort,
                        // Prefer allocation alias (public/GCore IP) for EOS ADDRESS_s.
                        'public_host' => $publicHost !== '' ? $publicHost : null,
                    ], static fn ($value) => $value !== null && $value !== ''),
                ]
            );
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
    }
}
