<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonCommandRepository setNode(\Pterodactyl\Models\Node $node)
 * @method \Pterodactyl\Repositories\Wings\DaemonCommandRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonCommandRepository extends DaemonRepository
{
    /**
     * Sends a command or multiple commands to a running server instance.
     *
     * When $silent is true, Wings will avoid writing the command/response to the
     * user-facing console (RCON path). Stdin games may still echo from the process.
     *
     * When $transmission is "stdin", Wings always writes to container stdin and
     * ignores the egg RCON setting — used for panel-enforced commands.
     *
     * @throws DaemonConnectionException
     */
    public function send(array|string $command, bool $silent = false, ?string $transmission = null): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        $payload = [
            'commands' => is_array($command) ? $command : [$command],
            'silent' => $silent,
        ];

        if (!is_null($transmission)) {
            $payload['transmission'] = $transmission;
        }

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/commands', $this->server->uuid),
                [
                    'json' => $payload,
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
