<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Helpers\ClientIp;
use Pterodactyl\Services\Firewall\SftpFirewallGrantService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\SftpFirewallRequest;

class SftpFirewallController extends ClientApiController
{
    public function __construct(private SftpFirewallGrantService $grantService)
    {
        parent::__construct();
    }

    /**
     * Return current temporary SFTP firewall grant status for this server's node.
     */
    public function index(SftpFirewallRequest $request, Server $server): JsonResponse
    {
        $server->loadMissing('node');
        $enabled = $server->node->sftpFirewallEnabled();
        $grant = $enabled
            ? $this->grantService->activeGrantForUserOnNode($request->user(), $server->node)
            : null;

        return new JsonResponse([
            'object' => 'sftp_firewall_access',
            'attributes' => $this->attributes($server, $enabled, $grant?->client_ip, $grant?->expires_at?->toIso8601String()),
        ]);
    }

    /**
     * Open temporary firewall access for the requester's IP.
     */
    public function store(SftpFirewallRequest $request, Server $server): JsonResponse
    {
        $server->loadMissing('node');
        $clientIp = ClientIp::from($request);

        $result = $this->grantService->grantForServer($server, $request->user(), $clientIp);
        $grant = $result['grant'];

        Activity::event($result['created'] ? 'server:sftp.firewall-grant' : 'server:sftp.firewall-extend')
            ->property(['ip' => $grant->client_ip, 'expires_at' => $grant->expires_at?->toIso8601String()])
            ->log();

        return new JsonResponse([
            'object' => 'sftp_firewall_access',
            'attributes' => $this->attributes(
                $server,
                true,
                $grant->client_ip,
                $grant->expires_at?->toIso8601String()
            ),
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Revoke the active temporary SFTP firewall grant for this user on the node.
     */
    public function delete(SftpFirewallRequest $request, Server $server): JsonResponse
    {
        $server->loadMissing('node');
        $grant = $this->grantService->activeGrantForUserOnNode($request->user(), $server->node);

        if ($grant) {
            $this->grantService->revokeGrant($grant, true);

            Activity::event('server:sftp.firewall-revoke')
                ->property(['ip' => $grant->client_ip])
                ->log();
        }

        return new JsonResponse([
            'object' => 'sftp_firewall_access',
            'attributes' => $this->attributes($server, $server->node->sftpFirewallEnabled(), null, null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Server $server, bool $enabled, ?string $clientIp, ?string $expiresAt): array
    {
        return [
            'enabled' => $enabled,
            'client_ip' => $clientIp,
            'detected_ip' => ClientIp::from(request()),
            'expires_at' => $expiresAt,
            'ttl_minutes' => (int) $server->node->sftp_firewall_ttl_minutes,
            'sftp_host' => $server->node->getSftpPublicHost(),
            'sftp_port' => $server->node->getSftpPublicPort(),
        ];
    }
}
