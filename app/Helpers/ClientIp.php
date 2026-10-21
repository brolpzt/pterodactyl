<?php

namespace Pterodactyl\Helpers;

use Illuminate\Http\Request;

class ClientIp
{
    /**
     * Resolve the real client IP, preferring Cloudflare / CDN headers when the
     * immediate peer looks like a trusted reverse proxy.
     *
     * Behind Cloudflare, REMOTE_ADDR is an edge IP (e.g. 172.71.x.x) unless
     * TRUSTED_PROXIES is configured. CF-Connecting-IP is set by Cloudflare to the
     * original visitor address and is the most reliable signal in that setup.
     */
    public static function from(Request $request): string
    {
        $remoteAddr = self::canonicalize($request->server->get('REMOTE_ADDR'));
        $trustForwarded = $remoteAddr !== null && (
            self::isCloudflareEdge($remoteAddr)
            || self::isPrivateOrLocal($remoteAddr)
        );

        if ($trustForwarded) {
            foreach ([
                $request->header('CF-Connecting-IP'),
                $request->header('True-Client-IP'),
            ] as $candidate) {
                $ip = self::canonicalize($candidate);
                if ($ip !== null && !self::isCloudflareEdge($ip)) {
                    return $ip;
                }
            }

            $forwarded = $request->header('X-Forwarded-For');
            if (is_string($forwarded) && $forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);
                $ip = self::canonicalize($first);
                if ($ip !== null && !self::isCloudflareEdge($ip)) {
                    return $ip;
                }
            }
        }

        $ip = self::canonicalize($request->ip());
        if ($ip !== null && !self::isCloudflareEdge($ip)) {
            return $ip;
        }

        return $remoteAddr ?? $ip ?? '0.0.0.0';
    }

    /**
     * Normalize an IP to a canonical form (compress IPv6) for storage / comparisons.
     */
    public static function canonicalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        // Strip IPv6 zone id (e.g. fe80::1%eth0) if present.
        if (str_contains($value, '%')) {
            $value = explode('%', $value, 2)[0];
        }

        if ($value === '' || !filter_var($value, FILTER_VALIDATE_IP)) {
            return null;
        }

        $packed = @inet_pton($value);
        if ($packed === false) {
            return null;
        }

        $canonical = @inet_ntop($packed);

        return is_string($canonical) ? $canonical : null;
    }

    public static function isIpv6(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public static function isPublic(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    public static function isCloudflareEdge(string $ip): bool
    {
        $ip = self::canonicalize($ip);
        if ($ip === null) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::ipv4InCloudflare($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::ipv6InCloudflare($ip);
        }

        return false;
    }

    private static function ipv4InCloudflare(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        // Cloudflare IPv4 ranges — https://www.cloudflare.com/ips-v4/
        $ranges = [
            ['173.245.48.0', 20],
            ['103.21.244.0', 22],
            ['103.22.200.0', 22],
            ['103.31.4.0', 22],
            ['141.101.64.0', 18],
            ['108.162.192.0', 18],
            ['190.93.240.0', 20],
            ['188.114.96.0', 20],
            ['197.234.240.0', 22],
            ['198.41.128.0', 17],
            ['162.158.0.0', 15],
            ['104.16.0.0', 13],
            ['104.24.0.0', 14],
            ['172.64.0.0', 13],
            ['131.0.72.0', 22],
        ];

        foreach ($ranges as [$network, $bits]) {
            $mask = -1 << (32 - $bits);
            if (($long & $mask) === (ip2long($network) & $mask)) {
                return true;
            }
        }

        return false;
    }

    private static function ipv6InCloudflare(string $ip): bool
    {
        // Cloudflare IPv6 ranges — https://www.cloudflare.com/ips-v6/
        $ranges = [
            ['2400:cb00::', 32],
            ['2606:4700::', 32],
            ['2803:f800::', 32],
            ['2405:b500::', 32],
            ['2405:8100::', 32],
            ['2a06:98c0::', 29],
            ['2c0f:f248::', 32],
        ];

        foreach ($ranges as [$network, $bits]) {
            if (self::ipv6InCidr($ip, $network, $bits)) {
                return true;
            }
        }

        return false;
    }

    private static function ipv6InCidr(string $ip, string $network, int $bits): bool
    {
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($network);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== 16) {
            return false;
        }

        $fullBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (~((1 << (8 - $remainingBits)) - 1)) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($netBin[$fullBytes]) & $mask);
    }

    private static function isPrivateOrLocal(string $ip): bool
    {
        return !self::isPublic($ip);
    }
}
