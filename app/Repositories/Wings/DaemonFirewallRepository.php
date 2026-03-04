<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonFirewallRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonFirewallRepository extends DaemonRepository
{
    /**
     * Adds a firewall rule (ban) on the Wings daemon for the given server.
     * Wings will execute iptables to drop traffic from the given IP
     * targeting the server's IP:port.
     *
     * @throws DaemonConnectionException
     */
    public function addRule(string $ip, string $reason = ''): void
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $this->getHttpClient()->post(
                "/api/servers/{$this->server->uuid}/firewall",
                [
                    'json' => [
                        'ip'          => $ip,
                        'reason'      => $reason,
                        'server_ip'   => $this->server->allocation->ip,
                        'server_port' => $this->server->allocation->port,
                    ],
                ]
            );
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Removes a firewall rule (unban) on the Wings daemon for the given server.
     *
     * @throws DaemonConnectionException
     */
    public function removeRule(string $ip): void
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $this->getHttpClient()->delete(
                "/api/servers/{$this->server->uuid}/firewall",
                [
                    'json' => [
                        'ip'          => $ip,
                        'server_ip'   => $this->server->allocation->ip,
                        'server_port' => $this->server->allocation->port,
                    ],
                ]
            );
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Removes all firewall rules for this server on the Wings daemon.
     * Called when a server is being deleted.
     *
     * @throws DaemonConnectionException
     */
    public function flushRules(): void
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $this->getHttpClient()->delete(
                "/api/servers/{$this->server->uuid}/firewall/flush"
            );
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
