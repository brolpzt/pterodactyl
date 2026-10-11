<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\Node;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class DaemonNftablesRepository extends DaemonRepository
{
    /**
     * Replace the node's managed nftables port chain with the desired rules.
     *
     * @param  list<array{ip: string, port: int, proto: string}>  $rules
     *
     * @throws DaemonConnectionException
     */
    public function sync(array $rules): void
    {
        Assert::isInstanceOf($this->node, Node::class);

        try {
            $this->getHttpClient()->put('/api/system/nftables', [
                'json' => [
                    'rules' => array_values($rules),
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
