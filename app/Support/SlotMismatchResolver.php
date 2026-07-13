<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\EnvironmentService;

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
        return self::parseSlotsFromEnvironment(app(EnvironmentService::class)->handle($server));
    }

    /**
     * @param array<string, mixed> $environment
     */
    public static function parseSlotsFromEnvironment(array $environment): ?int
    {
        foreach (self::SLOTS_ENV_FALLBACKS as $key) {
            $value = trim((string) ($environment[$key] ?? ''));
            if ($value !== '' && ctype_digit($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
