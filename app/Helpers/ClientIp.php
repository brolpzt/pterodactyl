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
        $remoteAddr = self::normalize($request->server->get('REMOTE_ADDR'));
        $trustForwarded = $remoteAddr !== null && (
            self::isCloudflareEdge($remoteAddr)
            || self::isPrivateOrLocal($remoteAddr)
        );

        if ($trustForwarded) {
            foreach ([
                $request->header('CF-Connecting-IP'),
                $request->header('True-Client-IP'),
            ] as $candidate) {
                $ip = self::normalize($candidate);
                if ($ip !== null && !self::isCloudflareEdge($ip)) {
                    return $ip;
                }
            }

            $forwarded = $request->header('X-Forwarded-For');
            if (is_string($forwarded) && $forwarded !== '') {
                $first = trim(explode(',', $forwarded)[0]);
                $ip = self::normalize($first);
                if ($ip !== null && !self::isCloudflareEdge($ip)) {
                    return $ip;
                }
            }
        }

        $ip = self::normalize($request->ip());
        if ($ip !== null && !self::isCloudflareEdge($ip)) {
            return $ip;
        }

        return $remoteAddr ?? $ip ?? '0.0.0.0';
    }

    public static function isCloudflareEdge(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

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

    private static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || !filter_var($value, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $value;
    }

    private static function isPrivateOrLocal(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
