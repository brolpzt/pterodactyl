<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\Server;

class SlotMismatchResolver
{
    public const SLOTS_ENV = 'SLOTS';

    /**
     * Resolve slot mismatch metadata for a game query response.
     *
     * A mismatch is reported only when the game advertises strictly more slots
     * than the panel SLOTS limit (less than or equal is allowed).
     *
     * @return array{
     *     configured: int|null,
     *     reported: int,
     *     env_variable: string,
     *     mismatch: bool,
     *     warn_enabled: bool,
     *     show_warning: bool,
     * }
     */
    public static function resolve(Server $server, bool $online, int $reportedMaxPlayers): array
    {
        $server->loadMissing('egg');

        $warnEnabled = self::resolveWarnEnabled($server);
        $configured = self::resolveConfiguredSlots($server);

        $mismatch = $online
            && $configured !== null
            && $reportedMaxPlayers > 0
            && $reportedMaxPlayers > $configured;

        return [
            'configured' => $configured,
            'reported' => $reportedMaxPlayers,
            'env_variable' => self::SLOTS_ENV,
            'mismatch' => $mismatch,
            'warn_enabled' => $warnEnabled,
            'show_warning' => $mismatch && $warnEnabled,
        ];
    }

    public static function resolveWarnEnabled(Server $server): bool
    {
        $server->loadMissing('egg');

        $override = $server->getAttributes()['warn_slot_mismatch'] ?? null;
        if ($override !== null) {
            return (bool) $override;
        }

        return (bool) ($server->egg->warn_slot_mismatch ?? false);
    }

    public static function resolveConfiguredSlots(Server $server): ?int
    {
        $value = self::resolveVariableValue($server, self::SLOTS_ENV);
        if ($value === null || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function resolveVariableValue(Server $server, string $envVariable): ?string
    {
        foreach ($server->variables as $variable) {
            if ($variable->env_variable !== $envVariable) {
                continue;
            }

            $value = $variable->server_value ?? $variable->default_value;

            return trim((string) $value);
        }

        return null;
    }
}
