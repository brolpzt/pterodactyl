<?php

namespace Pterodactyl\Services\Steam\Workshop;

use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class WorkshopAddonScriptRunner
{
    private const CONTAINER_IMAGE = 'ghcr.io/pterodactyl/installers:debian';

    public function __construct(
        private DaemonPowerRepository $powerRepository,
        private DaemonServerRepository $serverRepository,
    ) {
    }

    public function run(Server $server, string $script, bool $stopServer = true): void
    {
        if ($stopServer) {
            try {
                $this->powerRepository->setServer($server)->send('stop');
            } catch (DaemonConnectionException) {
                // Server may already be offline.
            }
        }

        try {
            $this->serverRepository->setServer($server)->executeAddon([
                'script' => $script,
                'container_image' => self::CONTAINER_IMAGE,
                'entrypoint' => 'bash',
            ], 60 * 30);
        } catch (DaemonConnectionException $exception) {
            throw new DisplayException(
                'Não foi possível executar a sincronização Workshop no servidor. Verifique se o Wings está atualizado e se o servidor está acessível.',
                $exception
            );
        }
    }
}
