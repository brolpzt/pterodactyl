<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\Server;

class HostnameBrandingResolver
{
    /**
     * @return array{
     *     hostname: string|null,
     *     compliant: bool,
     *     mismatch: bool,
     *     warn_enabled: bool,
     *     show_warning: bool,
     * }
     */
    public static function resolve(Server $server, bool $online, ?string $hostname): array
    {
        $server->loadMissing('egg');

        $warnEnabled = self::resolveWarnEnabled($server);
        $cleanHostname = self::stripHostnameColors($hostname);
        $compliant = self::hostnameContainsBranding($cleanHostname);

        $mismatch = $online && $cleanHostname !== '' && !$compliant;

        return [
            'hostname' => $hostname,
            'compliant' => $compliant,
            'mismatch' => $mismatch,
            'warn_enabled' => $warnEnabled,
            'show_warning' => $mismatch && $warnEnabled,
        ];
    }

    public static function resolveWarnEnabled(Server $server): bool
    {
        $server->loadMissing('egg');

        $override = $server->getAttributes()['warn_hostname_branding'] ?? null;
        if ($override !== null) {
            return (bool) $override;
        }

        return (bool) ($server->egg->warn_hostname_branding ?? false);
    }

    public static function hostnameContainsBranding(?string $hostname): bool
    {
        $cleaned = self::stripHostnameColors($hostname);
        if ($cleaned === '') {
            return false;
        }

        $compact = strtolower(preg_replace('/[^a-z0-9]/', '', $cleaned) ?? '');
        if (str_contains($compact, 'hostgamer')) {
            return true;
        }

        return (bool) preg_match('/host(?:[\s\W_]+)gamer/i', $cleaned);
    }

    public static function stripHostnameColors(?string $hostname): string
    {
        if ($hostname === null || trim($hostname) === '') {
            return '';
        }

        $value = str_replace('^^', "\u{E000}", $hostname);
        $value = preg_replace('/\^[0-9a-zA-Z]/', '', $value) ?? '';
        $value = str_replace("\u{E000}", '^', $value);
        $value = preg_replace('/&[0-9a-z]/i', '', $value) ?? '';
        $value = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }
}
