<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\FastDlNode;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

class FastDlSyncService
{
    /**
     * FastDlSyncService constructor.
     */
    public function __construct(
        protected DaemonServerRepository $daemonServerRepository,
        protected Encrypter $encrypter
    ) {
    }

    /**
     * Trigger a FastDL synchronization for a server.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function handle(Server $server): void
    {
        // Check if FastDL is enabled for the server and egg
        if (!$server->fastdl_enabled) {
            throw new DisplayException('FastDL is not enabled for this server.');
        }

        if (!in_array('fastdl', $server->egg->features ?? [])) {
            throw new DisplayException('The Egg assigned to this server does not support FastDL (missing "fastdl" feature).');
        }

        // Find a FastDL node in the server's location
        $node = FastDlNode::where('location_id', $server->location_id)
            ->where('is_active', true)
            ->first();

        if (!$node) {
            throw new DisplayException('No active FastDL node is configured for this server\'s location.');
        }

        // Prepare credentials
        $password = $node->password ? $this->encrypter->decrypt($node->password) : null;
        $privateKey = $node->private_key ? $this->encrypter->decrypt($node->private_key) : null;

        // Send request to Wings
        $this->daemonServerRepository->setServer($server)->syncFastDl([
            'host' => $node->fqdn,
            'port' => $node->port,
            'user' => $node->username,
            'password' => $password,
            'private_key' => $privateKey,
            'remote_path' => $node->remote_path,
            'sync_patterns' => $node->sync_patterns,
        ]);
    }
}
