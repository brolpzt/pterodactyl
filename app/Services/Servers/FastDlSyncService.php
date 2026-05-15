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
        if (!$server->fastdl_enabled) {
            throw new DisplayException('FastDL is not enabled for this server.');
        }

        if (!in_array('fastdl', $server->egg->features ?? [])) {
            throw new DisplayException('The Egg assigned to this server does not support FastDL (missing "fastdl" feature).');
        }

        $node = FastDlNode::where('location_id', $server->node->location_id)
            ->where('is_active', true)
            ->first();

        if (!$node) {
            throw new DisplayException('No active FastDL node is configured for this server\'s location.');
        }

        $payload = [
            'storage_type' => $node->storage_type,
            'remote_path' => $node->remote_path,
            'sync_patterns' => $node->sync_patterns,
        ];

        if ($node->isS3()) {
            $payload = array_merge($payload, [
                'bucket' => $node->bucket,
                'endpoint' => $node->endpoint,
                'region' => $node->region ?? 'auto',
                'access_key' => $this->encrypter->decrypt($node->access_key),
                'secret_key' => $this->encrypter->decrypt($node->secret_key),
                'use_path_style_endpoint' => $node->use_path_style_endpoint,
            ]);
        } else {
            $password = $node->password ? $this->encrypter->decrypt($node->password) : null;
            $privateKey = $node->private_key ? $this->encrypter->decrypt($node->private_key) : null;

            if (!$password && !$privateKey) {
                throw new DisplayException('The FastDL node has no SSH credentials configured.');
            }

            $payload = array_merge($payload, [
                'host' => $node->fqdn,
                'port' => $node->port,
                'user' => $node->username,
                'password' => $password,
                'private_key' => $privateKey,
            ]);
        }

        $this->daemonServerRepository->setServer($server)->syncFastDl($payload);
    }
}
