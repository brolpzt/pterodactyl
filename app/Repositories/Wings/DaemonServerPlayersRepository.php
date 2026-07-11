<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonServerPlayersRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonServerPlayersRepository extends DaemonRepository
{
    /**
     * Fetches connected players by sending `status` to the server console and parsing logs.
     *
     * @throws DaemonConnectionException
     */
    public function list(): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->get(
                "/api/servers/{$this->server->uuid}/players"
            );
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
    }
}
