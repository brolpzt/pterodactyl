<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

/**
 * FastDL install manifests live on Wings: they list the stock files the egg
 * install delivered, which FastDL syncs skip. New installs record one
 * automatically; generate() builds one for an existing server from a reference
 * install in a scratch directory on the node (removed afterwards).
 */
class FastDlManifestService
{
    public function __construct(private DaemonServerRepository $daemonServerRepository)
    {
    }

    public function supports(Server $server): bool
    {
        return $server->fastdl_enabled && in_array('fastdl', $server->egg->features ?? [], true);
    }

    /**
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function status(Server $server): array
    {
        return $this->daemonServerRepository->setServer($server)->getFastDlManifest();
    }

    /**
     * @throws DisplayException
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function generate(Server $server): array
    {
        if (!$this->supports($server)) {
            throw new DisplayException('FastDL is not enabled for this server or its egg.');
        }
        if (!$server->isInstalled()) {
            throw new DisplayException('The server has not finished installing.');
        }

        return $this->daemonServerRepository->setServer($server)->generateFastDlManifest();
    }
}
