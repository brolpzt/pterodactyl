<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Server;

class SlotMismatchResolver
{
    public const SLOTS_ENV = 'SLOTS';

    /** @var list<string> */
    private const SLOTS_ENV_FALLBACKS = [
        self::SLOTS_ENV,
        'MAX_CLIENTS',
    ];

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
        $server->loadMissing(['egg', 'variables']);

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
        $server->loadMissing('variables');

        foreach (self::SLOTS_ENV_FALLBACKS as $env) {
            $variable = self::pickSlotVariable($server, $env);
            if ($variable === null) {
                continue;
            }

            $value = self::resolveVariableEffectiveValue($variable);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public static function pickSlotVariable(Server $server, string $env): ?EggVariable
    {
        $matches = $server->variables
            ->filter(static fn (EggVariable $variable) => $variable->env_variable === $env)
            ->sortByDesc('id')
            ->values();

        if ($matches->isEmpty()) {
            return null;
        }

        return $matches->first(static fn (EggVariable $variable) => $variable->user_viewable)
            ?? $matches->first();
    }

    public static function resolveVariableEffectiveValue(EggVariable $variable): ?int
    {
        $value = trim((string) ($variable->server_value ?? $variable->default_value));
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }
}
