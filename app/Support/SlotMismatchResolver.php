<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\Server;

class SlotMismatchResolver
{
    public const WARN_ENV = 'WARN_SLOT_MISMATCH';

    public const SLOTS_ENV_VARIABLE = 'SLOTS_ENV_VARIABLE';

    /** @var list<string> */
    private const DEFAULT_SLOTS_ENV_CANDIDATES = [
        'MAX_CLIENTS',
        'MAX_PLAYERS',
        'SERVER_MAX_PLAYERS',
        'SLOTS',
    ];

    /**
     * Resolve slot mismatch metadata for a game query response.
     *
     * A mismatch is reported only when the game advertises strictly more slots
     * than the panel limit (less than or equal is allowed).
     *
     * @return array{
     *     configured: int|null,
     *     reported: int,
     *     env_variable: string|null,
     *     mismatch: bool,
     *     warn_enabled: bool,
     *     show_warning: bool,
     * }
     */
    public static function resolve(Server $server, bool $online, int $reportedMaxPlayers): array
    {
        $warnEnabled = self::resolveWarnEnabled($server);
        $slotsEnv = self::resolveSlotsEnvName($server);
        $configured = self::resolveConfiguredSlots($server, $slotsEnv);

        $mismatch = $online
            && $configured !== null
            && $reportedMaxPlayers > 0
            && $reportedMaxPlayers > $configured;

        return [
            'configured' => $configured,
            'reported' => $reportedMaxPlayers,
            'env_variable' => $slotsEnv,
            'mismatch' => $mismatch,
            'warn_enabled' => $warnEnabled,
            'show_warning' => $mismatch && $warnEnabled,
        ];
    }

    public static function resolveWarnEnabled(Server $server): bool
    {
        $value = self::resolveVariableValue($server, self::WARN_ENV);

        if ($value === null) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function resolveSlotsEnvName(Server $server): ?string
    {
        $explicit = self::resolveVariableValue($server, self::SLOTS_ENV_VARIABLE);
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        foreach (self::DEFAULT_SLOTS_ENV_CANDIDATES as $candidate) {
            if (self::variableExistsOnEgg($server, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function resolveConfiguredSlots(Server $server, ?string $slotsEnv = null): ?int
    {
        $slotsEnv ??= self::resolveSlotsEnvName($server);
        if ($slotsEnv === null) {
            return null;
        }

        $value = self::resolveVariableValue($server, $slotsEnv);
        if ($value === null || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function variableExistsOnEgg(Server $server, string $envVariable): bool
    {
        foreach ($server->variables as $variable) {
            if ($variable->env_variable === $envVariable) {
                return true;
            }
        }

        return false;
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
