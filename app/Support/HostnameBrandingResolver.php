<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;

class HostnameBrandingResolver
{
    public const BRANDING_ENV = 'BRANDING';

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

    /**
     * Enabled only when the egg defines BRANDING and the server value is 1.
     */
    public static function resolveWarnEnabled(Server $server): bool
    {
        $variable = self::pickBrandingVariable($server);
        if ($variable === null) {
            return false;
        }

        $value = trim((string) ($variable->server_value ?? $variable->default_value));

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function pickBrandingVariable(Server $server): ?EggVariable
    {
        if ($server->relationLoaded('variables')) {
            $server->unsetRelation('variables');
        }

        return $server->variables()
            ->where('egg_variables.env_variable', self::BRANDING_ENV)
            ->orderByDesc('egg_variables.id')
            ->first();
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
