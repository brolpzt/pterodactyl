<?php

namespace Pterodactyl\Services\Auth;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Exceptions\Auth\SsoAuthenticationException;

class SsoLoginService
{
    /**
     * Build the canonical payload string that must be signed by both systems.
     */
    public static function buildPayload(string $user, string $server, int $expires, string $nonce): string
    {
        return http_build_query([
            'user' => $user,
            'server' => $server,
            'expires' => (string) $expires,
            'nonce' => $nonce,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Generate an HMAC signature for an SSO link payload.
     */
    public static function sign(string $user, string $server, int|string $expires, string $nonce, string $secret): string
    {
        return hash_hmac('sha256', self::buildPayload($user, $server, (int) $expires, $nonce), $secret);
    }

    /**
     * Validate query parameters and return the authenticated user and target server.
     *
     * @return array{0: User, 1: Server}
     *
     * @throws SsoAuthenticationException
     */
    public function authenticate(array $query): array
    {
        if (!config('sso.enabled', false)) {
            throw new SsoAuthenticationException('SSO is disabled.');
        }

        $secret = config('sso.secret');
        if (empty($secret)) {
            throw new SsoAuthenticationException('SSO secret is not configured.');
        }

        $userRef = (string) ($query['user'] ?? '');
        $serverRef = (string) ($query['server'] ?? '');
        $expires = $query['expires'] ?? null;
        $nonce = (string) ($query['nonce'] ?? '');
        $signature = (string) ($query['signature'] ?? '');

        if ($userRef === '' || $serverRef === '' || $expires === null || $nonce === '' || $signature === '') {
            throw new SsoAuthenticationException('Missing required SSO parameters.');
        }

        if (!is_numeric($expires)) {
            throw new SsoAuthenticationException('Invalid expiration timestamp.');
        }

        $expires = (int) $expires;
        $now = time();

        if ($expires < $now) {
            throw new SsoAuthenticationException('SSO link has expired.');
        }

        $maxTtl = max(1, (int) config('sso.token_ttl', 60));
        $clockSkew = max(0, (int) config('sso.clock_skew', 30));
        if ($expires > $now + $maxTtl + $clockSkew) {
            throw new SsoAuthenticationException('SSO link expiration is too far in the future.');
        }

        if (strlen($nonce) < 16 || strlen($nonce) > 128 || !ctype_xdigit($nonce)) {
            throw new SsoAuthenticationException('Invalid nonce format.');
        }

        // Sign with the raw query values (before int cast side-effects) to match external panels.
        $expected = self::sign($userRef, $serverRef, (string) $expires, $nonce, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new SsoAuthenticationException('Invalid SSO signature.');
        }

        $user = $this->resolveUser($userRef);
        if (!$user) {
            throw new SsoAuthenticationException('User not found.');
        }

        $server = $this->resolveServer($user, $serverRef);
        if (!$server) {
            throw new SsoAuthenticationException('Server not found or not accessible.');
        }

        $nonceKey = 'sso:nonce:' . $nonce;
        $nonceTtl = max($expires - $now, 1);
        if (!Cache::add($nonceKey, true, $nonceTtl)) {
            throw new SsoAuthenticationException('SSO link has already been used.');
        }

        return [$user, $server];
    }

    private function resolveUser(string $reference): ?User
    {
        $mode = config('sso.user_identifier', 'external_id');

        if ($mode === 'id') {
            if (!ctype_digit($reference)) {
                return null;
            }

            return User::query()->find((int) $reference);
        }

        return User::query()->where('external_id', $reference)->first();
    }

    private function resolveServer(User $user, string $reference): ?Server
    {
        $query = $user->accessibleServers()->where(function ($builder) use ($reference) {
            $builder->where('servers.uuidShort', $reference)
                ->orWhere('servers.uuid', $reference);

            if (config('sso.allow_server_external_id', false)) {
                $builder->orWhere('servers.external_id', $reference);
            }
        });

        return $query->first();
    }
}
