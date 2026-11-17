<?php

namespace Pterodactyl\Services\Xdp;

use Pterodactyl\Models\Egg;
use Pterodactyl\Support\GameDigTypeResolver;

/**
 * Profiles of the node's XDP game firewall (game_firewall CLI) and the automatic
 * choice for an egg. The XDP program only filters UDP; TCP is always passed.
 */
final class XdpProfile
{
    public const PROFILES = ['source', 'cs16', 'pluto', 'cod2', 'ts3', 'generic'];

    /** Slot value meaning "never protect this port with XDP". */
    public const NONE = 'none';

    public const LABELS = [
        'source' => 'Source / Steam A2S (ETS2, ATS, Rust, CS:GO, TF2, GMod)',
        'cs16' => 'GoldSrc (CS 1.6, Half-Life, ReHLDS)',
        'pluto' => 'Plutonium (BO2, MW3)',
        'cod2' => 'Call of Duty 2 / CoD2x',
        'ts3' => 'TeamSpeak 3 (voz UDP)',
        'generic' => 'UDP genérico',
    ];

    /** GoldSrc games (the cs16 filter follows the GoldSrc/ReHLDS protocol). */
    private const GOLDSRC = [
        'cs16', 'counter-strike', 'counterstrike16', 'goldsource', 'cstrike', 'cs', 'cscz', 'cz',
        'half-life', 'hl', 'hl1', 'opfor', 'hlof', 'half-life-opposing-force', 'dmc', 'deathmatch-classic',
        'ricochet', 'tfc', 'team-fortress-classic', 'dod', 'day-of-defeat', 'sven-coop', 'svencoop',
    ];

    /** Source engine and other Steam A2S games the source filter is meant for. */
    private const SOURCE = [
        'ets2', 'ats', 'rust', 'counter-strike-source', 'css', 'counter-strike-go', 'csgo', 'cs2',
        'counter-strike-2', 'garrys-mod', 'gmod', 'team-fortress-2', 'tf2', 'l4d', 'l4d2', 'left-4-dead',
        'left-4-dead-2', 'hl2dm', 'half-life-2-deathmatch', 'half-life-deathmatch-source', 'hldms', 'dods',
        'day-of-defeat-source', 'insurgency', 'ins', 'black-mesa', 'bm', 'bms', 'nmrih', 'no-more-room-in-hell',
        'zps', 'zombie-panic-source', 'fof', 'fistful-of-frags', 'nt', 'neotokyo', 'nd', 'nuclear-dawn',
        'pvkii', 'pirates-vikings-and-knights-ii', 'asrd', 'alien-swarm-reactive-drop', 'bb2', 'brainbread-2',
    ];

    /**
     * Profile chosen automatically for the egg's game ports.
     */
    public static function auto(?Egg $egg, bool $isTs3 = false): string
    {
        // TeamSpeak is not a Wings query type, so the resolver below does not return it.
        $raw = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $egg?->gamedig) ?? '');
        if ($isTs3 || in_array($raw, ['ts3', 'teamspeak', 'teamspeak3'], true) || preg_match('/team\s*speak/i', (string) $egg?->name)) {
            return 'ts3';
        }

        $type = GameDigTypeResolver::resolve($egg?->gamedig, $egg?->name);

        return match (true) {
            $type === null => 'generic',
            $type === GameDigTypeResolver::PLUTONIUM => 'pluto',
            $type === 'teamspeak3' => 'ts3',
            $type === 'cod2' => 'cod2',
            in_array($type, self::GOLDSRC, true) => 'cs16',
            in_array($type, self::SOURCE, true) => 'source',
            default => 'generic',
        };
    }

    /**
     * Normalizes a port slot's xdp_profile: null (automatic), 'none' or a profile;
     * false when the value is not valid.
     */
    public static function normalizeSlotValue(mixed $value): string|false|null
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === 'auto') {
            return null;
        }

        return $value === self::NONE || in_array($value, self::PROFILES, true) ? $value : false;
    }
}
