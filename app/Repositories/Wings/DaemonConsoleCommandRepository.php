<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonConsoleCommandRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonConsoleCommandRepository extends DaemonRepository
{
    /**
     * Sends a command to the server console and returns recent log output from Wings.
     *
     * @throws DaemonConnectionException
     */
    public function execute(string $command): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->post(
                "/api/servers/{$this->server->uuid}/console-query",
                ['json' => ['command' => $command]]
            );
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);
    }
}
