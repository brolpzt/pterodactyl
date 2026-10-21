<?php

namespace Pterodactyl\Services\Firewall;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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
    /** Max concurrent active IP grants per user on a node (shared logins). */
    public const MAX_ACTIVE_GRANTS_PER_USER = 10;

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

        $clientIp = ClientIp::canonicalize($clientIp);
        if ($clientIp === null) {
            throw new BadRequestHttpException('Could not determine a valid client IP address.');
        }

        if (!ClientIp::isPublic($clientIp) || ClientIp::isCloudflareEdge($clientIp)) {
            throw new BadRequestHttpException(
                'Detected a proxy or private IP instead of your real public address. Refresh and try again, or enter your public IP manually.'
            );
        }

        $port = $node->getSftpFirewallPort();
        $ttl = max(5, (int) $node->sftp_firewall_ttl_minutes);
        $expiresAt = CarbonImmutable::now()->addMinutes($ttl);

        /** @var SftpFirewallGrant|null $existing */
        $existing = $this->activeGrantForUserIpOnNode($user, $node, $clientIp);

        if ($existing) {
            // Extend TTL for same IP; leave other IPs for this user alone.
            $existing->forceFill(['expires_at' => $expiresAt, 'server_id' => $server->id])->save();

            return ['grant' => $existing->fresh(), 'created' => false];
        }

        $activeCount = $this->activeGrantsForUserOnNode($user, $node)->count();
        if ($activeCount >= self::MAX_ACTIVE_GRANTS_PER_USER) {
            throw new BadRequestHttpException(sprintf(
                'Too many active SFTP firewall allows (%d). Remove an IP before adding another.',
                self::MAX_ACTIVE_GRANTS_PER_USER
            ));
        }

        return DB::transaction(function () use ($provider, $node, $user, $server, $clientIp, $port, $expiresAt) {
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

    /**
     * @return Collection<int, SftpFirewallGrant>
     */
    public function activeGrantsForUserOnNode(User $user, Node $node): Collection
    {
        return SftpFirewallGrant::query()
            ->where('user_id', $user->id)
            ->where('node_id', $node->id)
            ->where('status', SftpFirewallGrant::STATUS_ACTIVE)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->orderBy('id')
            ->get();
    }

    public function activeGrantForUserIpOnNode(User $user, Node $node, string $clientIp): ?SftpFirewallGrant
    {
        $clientIp = ClientIp::canonicalize($clientIp) ?? $clientIp;

        return SftpFirewallGrant::query()
            ->where('user_id', $user->id)
            ->where('node_id', $node->id)
            ->where('client_ip', $clientIp)
            ->where('status', SftpFirewallGrant::STATUS_ACTIVE)
            ->where('expires_at', '>', CarbonImmutable::now())
            ->latest('id')
            ->first();
    }

    /**
     * @deprecated Prefer activeGrantForUserIpOnNode / activeGrantsForUserOnNode
     */
    public function activeGrantForUserOnNode(User $user, Node $node): ?SftpFirewallGrant
    {
        return $this->activeGrantsForUserOnNode($user, $node)->last();
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
