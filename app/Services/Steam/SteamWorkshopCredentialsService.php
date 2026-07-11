<?php

namespace Pterodactyl\Services\Steam;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\EnvironmentService;

class SteamWorkshopCredentialsService
{
    public function __construct(private EnvironmentService $environmentService)
    {
    }

    /**
     * @return array{user: string, pass: string, auth: string, source: 'panel'|'server'}
     */
    public function forServer(Server $server): array
    {
        $panelUser = trim((string) config('pterodactyl.steam.workshop_user', ''));
        if ($panelUser !== '') {
            return [
                'user' => $panelUser,
                'pass' => (string) config('pterodactyl.steam.workshop_pass', ''),
                'auth' => (string) config('pterodactyl.steam.workshop_auth', ''),
                'source' => 'panel',
            ];
        }

        $server->loadMissing('variables');
        $environment = $this->environmentService->handle($server);

        return [
            'user' => trim((string) ($environment['STEAM_USER'] ?? '')),
            'pass' => (string) ($environment['STEAM_PASS'] ?? ''),
            'auth' => (string) ($environment['STEAM_AUTH'] ?? ''),
            'source' => 'server',
        ];
    }

    public function isConfiguredGlobally(): bool
    {
        return trim((string) config('pterodactyl.steam.workshop_user', '')) !== '';
    }

    public function isConfiguredForServer(Server $server): bool
    {
        return $this->forServer($server)['user'] !== '';
    }
}
