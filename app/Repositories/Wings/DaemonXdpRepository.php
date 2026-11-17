<?php

namespace Pterodactyl\Repositories\Wings;

use Pterodactyl\Models\Node;
use Webmozart\Assert\Assert;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class DaemonXdpRepository extends DaemonRepository
{
    /**
     * Make the node's XDP game firewall port map equal to $ports (port => profile).
     * Returns what changed (added / updated / removed / unchanged); nothing changes on a dry run.
     *
     * @param array<int|string, string> $ports
     *
     * @throws DaemonConnectionException
     */
    public function sync(array $ports, bool $dryRun = false): array
    {
        Assert::isInstanceOf($this->node, Node::class);

        $payload = [];
        foreach ($ports as $port => $profile) {
            $payload[(string) $port] = $profile;
        }

        try {
            $response = $this->getHttpClient()->put('/api/system/xdp', [
                'json' => [
                    // An empty map must still be an object for Wings.
                    'ports' => (object) $payload,
                    'dry_run' => $dryRun,
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody()->__toString(), true) ?: [];
    }

    /**
     * Ports the node currently protects (port => profile).
     *
     * @return array<int, string>
     *
     * @throws DaemonConnectionException
     */
    public function list(): array
    {
        Assert::isInstanceOf($this->node, Node::class);

        try {
            $response = $this->getHttpClient()->get('/api/system/xdp');
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }

        $data = json_decode($response->getBody()->__toString(), true);

        return is_array($data['ports'] ?? null) ? $data['ports'] : [];
    }
}
