<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Support\Collection;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Helpers\ClientIp;
use Pterodactyl\Models\SftpFirewallGrant;
use Pterodactyl\Services\Firewall\SftpFirewallGrantService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\SftpFirewallRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $detectedIp = ClientIp::from($request);
        $grants = $enabled
            ? $this->grantService->activeGrantsForUserOnNode($request->user(), $server->node)
            : collect();
        $mine = $enabled
            ? $this->grantService->activeGrantForUserIpOnNode($request->user(), $server->node, $detectedIp)
            : null;

        return new JsonResponse([
            'object' => 'sftp_firewall_access',
            'attributes' => $this->attributes($server, $enabled, $detectedIp, $mine, $grants),
        ]);
    }

    /**
     * Open temporary firewall access for the requester's IP.
     * Other active IPs for the same user on this node are kept.
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

        $grants = $this->grantService->activeGrantsForUserOnNode($request->user(), $server->node);

        return new JsonResponse([
            'object' => 'sftp_firewall_access',
            'attributes' => $this->attributes($server, true, $clientIp, $grant, $grants),
        ], $result['created'] ? 201 : 200);
    }

    /**
     * Revoke a temporary SFTP firewall grant.
     * Without ?ip=, revokes the current visitor IP. With ?ip=, revokes that IP if owned by the user.
     */
    public function delete(SftpFirewallRequest $request, Server $server): JsonResponse
    {
        $server->loadMissing('node');
        $detectedIp = ClientIp::from($request);
        $targetIp = $request->query('ip', $detectedIp);
        if (!is_string($targetIp) || !filter_var($targetIp, FILTER_VALIDATE_IP)) {
            throw new NotFoundHttpException('No matching SFTP firewall allow found for that IP.');
        }

        $grant = $this->grantService->activeGrantForUserIpOnNode($request->user(), $server->node, $targetIp);

        if (!$grant) {
            throw new NotFoundHttpException('No matching SFTP firewall allow found for that IP.');
        }

        $this->grantService->revokeGrant($grant, true);

        Activity::event('server:sftp.firewall-revoke')
            ->property(['ip' => $grant->client_ip])
            ->log();

        $grants = $this->grantService->activeGrantsForUserOnNode($request->user(), $server->node);
        $mine = $this->grantService->activeGrantForUserIpOnNode($request->user(), $server->node, $detectedIp);

        return new JsonResponse([
            'object' => 'sftp_firewall_access',
            'attributes' => $this->attributes(
                $server,
                $server->node->sftpFirewallEnabled(),
                $detectedIp,
                $mine,
                $grants
            ),
        ]);
    }

    /**
     * @param  Collection<int, SftpFirewallGrant>  $grants
     * @return array<string, mixed>
     */
    private function attributes(
        Server $server,
        bool $enabled,
        string $detectedIp,
        ?SftpFirewallGrant $mine,
        Collection $grants
    ): array {
        return [
            'enabled' => $enabled,
            'client_ip' => $mine?->client_ip,
            'detected_ip' => $detectedIp,
            'expires_at' => $mine?->expires_at?->toIso8601String(),
            'ttl_minutes' => (int) $server->node->sftp_firewall_ttl_minutes,
            'sftp_host' => $server->node->getSftpPublicHost(),
            'sftp_port' => $server->node->getSftpPublicPort(),
            'grants' => $grants->map(fn ($g) => [
                'ip' => $g->client_ip,
                'expires_at' => $g->expires_at?->toIso8601String(),
                'is_current' => $g->client_ip === $detectedIp,
            ])->values()->all(),
        ];
    }
}
