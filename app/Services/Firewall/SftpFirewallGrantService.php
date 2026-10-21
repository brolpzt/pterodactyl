<?php

namespace Pterodactyl\Services\Firewall;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Helpers\ClientIp;
use Pterodactyl\Models\SftpFirewallGrant;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SftpFirewallGrantService
{
    public function __construct(private FirewallAccessManager $manager)
    {
    }

    /**
     * @return array{grant: SftpFirewallGrant, created: bool}
     */
    public function grantForServer(Server $server, User $user, string $clientIp): array
    {
        $node = $server->node;
        $provider = $this->manager->forNode($node);

        if (!$provider->isConfigured($node)) {
            throw new BadRequestHttpException('SFTP firewall access is not configured on this node.');
        }

        if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
            throw new BadRequestHttpException('Could not determine a valid client IP address.');
        }

        if (ClientIp::isCloudflareEdge($clientIp)) {
            throw new BadRequestHttpException(
                'Detected a Cloudflare proxy IP instead of your real address. Refresh and try again.'
            );
        }

        $port = $node->getSftpFirewallPort();
        $ttl = max(5, (int) $node->sftp_firewall_ttl_minutes);
        $expiresAt = CarbonImmutable::now()->addMinutes($ttl);

        /** @var SftpFirewallGrant|null $existing */
        $existing = SftpFirewallGrant::query()
            ->where('user_id', $user->id)
            ->where('node_id', $node->id)
            ->where('status', SftpFirewallGrant::STATUS_ACTIVE)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->latest('id')
            ->first();

        if ($existing && $existing->client_ip === $clientIp) {
            // Extend TTL for same IP.
            $existing->forceFill(['expires_at' => $expiresAt, 'server_id' => $server->id])->save();

            return ['grant' => $existing->fresh(), 'created' => false];
        }

        return DB::transaction(function () use ($existing, $provider, $node, $user, $server, $clientIp, $port, $expiresAt) {
            if ($existing) {
                $this->revokeGrant($existing, false);
            }

            try {
                $externalId = $provider->grant(
                    $node,
                    $clientIp,
                    $port,
                    sprintf('Pterodactyl SFTP user=%d server=%d', $user->id, $server->id)
                );
            } catch (\Throwable $exception) {
                Log::warning('SFTP firewall grant failed', [
                    'node_id' => $node->id,
                    'user_id' => $user->id,
                    'ip' => $clientIp,
                    'error' => $exception->getMessage(),
                ]);

                throw new HttpException(502, 'Failed to open firewall access: ' . $exception->getMessage());
            }

            $grant = SftpFirewallGrant::query()->create([
                'user_id' => $user->id,
                'node_id' => $node->id,
                'server_id' => $server->id,
                'client_ip' => $clientIp,
                'provider' => $provider->key(),
                'external_rule_id' => $externalId,
                'status' => SftpFirewallGrant::STATUS_ACTIVE,
                'expires_at' => $expiresAt,
            ]);

            return ['grant' => $grant, 'created' => true];
        });
    }

    public function activeGrantForUserOnNode(User $user, Node $node): ?SftpFirewallGrant
    {
        return SftpFirewallGrant::query()
            ->where('user_id', $user->id)
            ->where('node_id', $node->id)
            ->where('status', SftpFirewallGrant::STATUS_ACTIVE)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->latest('id')
            ->first();
    }

    public function revokeGrant(SftpFirewallGrant $grant, bool $throw = true): void
    {
        if ($grant->status === SftpFirewallGrant::STATUS_REVOKED) {
            return;
        }

        $node = $grant->node ?? Node::query()->find($grant->node_id);
        if ($node && $grant->external_rule_id) {
            try {
                $this->manager->forNode($node)->revoke($node, $grant->external_rule_id);
            } catch (\Throwable $exception) {
                Log::warning('SFTP firewall revoke failed', [
                    'grant_id' => $grant->id,
                    'error' => $exception->getMessage(),
                ]);

                if ($throw) {
                    throw new ConflictHttpException('Failed to revoke firewall rule: ' . $exception->getMessage());
                }

                $grant->forceFill([
                    'last_error' => $exception->getMessage(),
                    'status' => SftpFirewallGrant::STATUS_REVOKED,
                    'revoked_at' => CarbonImmutable::now(),
                ])->save();

                return;
            }
        }

        $grant->forceFill([
            'status' => SftpFirewallGrant::STATUS_REVOKED,
            'revoked_at' => CarbonImmutable::now(),
            'last_error' => null,
        ])->save();
    }

    /**
     * @return array{revoked: int, failed: int}
     */
    public function expireDue(): array
    {
        $stats = ['revoked' => 0, 'failed' => 0];

        $due = SftpFirewallGrant::query()
            ->with('node')
            ->where('status', SftpFirewallGrant::STATUS_ACTIVE)
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->orderBy('id')
            ->get();

        foreach ($due as $grant) {
            try {
                $this->revokeGrant($grant, false);
                ++$stats['revoked'];
            } catch (\Throwable) {
                ++$stats['failed'];
            }
        }

        return $stats;
    }
}
