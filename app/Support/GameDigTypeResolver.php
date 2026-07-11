<?php

namespace Pterodactyl\Support;

class GameDigTypeResolver
{
    /**
     * Aliases comuns do campo gamedig → tipo de protocolo consultado pelo Wings (gjq).
     *
     * @var array<string, string>
     */
    private const GAMEDIG_ALIASES = [
        'callofduty' => 'cod',
        'callofdutyuo' => 'coduo',
        'callofduty2' => 'cod2',
        'callofduty4' => 'cod4',
        'cod' => 'cod',
        'coduo' => 'coduo',
        'counterstrike16' => 'cs16',
        'goldsource' => 'cs16',
        'cstrike' => 'cs16',
        'conditionzero' => 'cscz',
        'cz' => 'cscz',
        'modernwarfare3' => 'codmw3',
        'mw3' => 'codmw3',
        'medalofhonoralliedassault' => 'mohaa',
        'mohaa' => 'mohaa',
        'gtasa' => 'samp',
        'samp' => 'samp',
        'teamspeak3' => 'teamspeak3',
        'ts3' => 'teamspeak3',
        'minecraft' => 'minecraft-java',
        'mc' => 'minecraft-java',
        'bedrock' => 'minecraft-bedrock',
        'gmod' => 'garrys-mod',
        'tf2' => 'team-fortress-2',
        'css' => 'counter-strike-source',
        'csgo' => 'counter-strike-go',
    ];

    /**
     * Padrões do nome do egg quando gamedig não está configurado.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const EGG_NAME_PATTERNS = [
        ['/(call\s*of\s*duty\s*4|\bcod\s*4\b)/i', 'cod4'],
        ['/(call\s*of\s*duty\s*2|\bcod\s*2\b)/i', 'cod2'],
        ['/(call\s*of\s*duty\s*1|\bcod\s*1\b)/i', 'cod'],
        ['/(united\s*offensive|\bcoduo\b)/i', 'coduo'],
        ['/(modern\s*warfare\s*3|\bmw3\b)/i', 'codmw3'],
        ['/(condition\s*zero|\bcz\b)/i', 'cscz'],
        ['/(counter-?strike\s*1\.?6|\bcs\s*1\.?6\b|\bcs16\b)/i', 'cs16'],
        ['/(gta\s*san\s*andreas|\bsa-?mp\b|\bgtasa\b)/i', 'samp'],
        ['/(medal\s*of\s*honor|\bmohaa\b)/i', 'mohaa'],
    ];

    /**
     * Tipos suportados pelo Wings via gjq/gamejanitor e fallbacks de protocolo.
     *
     * @var array<int, string>
     */
    private const SUPPORTED_TYPES = [
        '7-days-to-die', '7d2d', '7dtd', 'alien-swarm-reactive-drop', 'ark', 'ark-survival-ascended',
        'ark-survival-evolved', 'arma-2', 'arma-3', 'arma-reforger', 'arma2', 'arma3', 'asa', 'ase',
        'asrd', 'atlas', 'barotrauma', 'battalion', 'battalion-1944', 'bb2', 'bedrock', 'beyond-the-wire',
        'black-mesa', 'bm', 'bms', 'brainbread-2', 'btw', 'cod', 'cod-mw2', 'cod-waw', 'cod2', 'cod4',
        'cod4x', 'codmw3', 'coduo', 'conan', 'conan-exiles', 'counter-strike', 'counter-strike-2',
        'counter-strike-go', 'counter-strike-source', 'counterstrike16', 'cs', 'cs16', 'cs2', 'cscz',
        'csgo', 'css', 'cstrike', 'cz', 'dark-and-light', 'day-of-defeat', 'day-of-defeat-source',
        'day-of-infamy', 'dayz', 'deathmatch-classic', 'dmc', 'dnl', 'dod', 'dods', 'doi', 'egs',
        'empyrion', 'enshrouded', 'et', 'evrima', 'fistful-of-frags', 'fivem', 'fof', 'forest',
        'garrys-mod', 'gmod', 'goldsource', 'gta5', 'gtasa', 'half-life', 'half-life-2-deathmatch',
        'half-life-deathmatch-source', 'half-life-opposing-force', 'hell-let-loose', 'hl', 'hl1',
        'hl2dm', 'hldms', 'hll', 'hlof', 'holdfast', 'holdfast-naw', 'hurtworld', 'ins', 'inss',
        'insurgency', 'insurgency-sandstorm', 'isle', 'iw4x', 'java', 'kf2', 'killing-floor-2',
        'l4d', 'l4d2', 'left-4-dead', 'left-4-dead-2', 'mc', 'mcbe', 'mcje', 'minecraft',
        'minecraft-bedrock', 'minecraft-java', 'miscreated', 'moe', 'mohaa', 'mordhau', 'mw2', 'mw3',
        'myth-of-empires', 'natural-selection-2', 'nd', 'neotokyo', 'nmrih', 'no-more-room-in-hell',
        'ns2', 'nt', 'nuclear-dawn', 'opfor', 'palworld', 'pirates-vikings-and-knights-ii', 'pixark',
        'project-zomboid', 'pvkii', 'pw', 'pz', 'ql', 'quake-live', 'red-orchestra-2', 'reforger',
        'ricochet', 'rising-storm-2', 'ro2', 'rs2', 'rust', 's44', 'samp', 'se', 'sons-of-the-forest',
        'sotf', 'soulmask', 'space-engineers', 'squad', 'squad-44', 'starbound', 'sven-coop', 'svencoop',
        't4', 'team-fortress-2', 'team-fortress-classic', 'terraria', 'tf2', 'tfc', 'the-forest',
        'the-front', 'the-isle', 'the-isle-evrima', 'tshock', 'unturned', 'v-rising', 'valheim',
        'vrising', 'waw', 'wet', 'wolfenstein-et', 'zombie-panic-source', 'zomboid', 'zps',
    ];

    public static function resolve(?string $gamedig, ?string $eggName = null): ?string
    {
        $candidates = [];

        if ($gamedig !== null && trim($gamedig) !== '') {
            $candidates[] = self::normalizeSlug($gamedig);
        }

        if ($eggName !== null && trim($eggName) !== '') {
            foreach (self::EGG_NAME_PATTERNS as [$pattern, $type]) {
                if (preg_match($pattern, $eggName)) {
                    $candidates[] = $type;
                    break;
                }
            }

            $candidates[] = self::normalizeSlug($eggName);
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            $mapped = self::GAMEDIG_ALIASES[$candidate] ?? $candidate;

            if (self::isSupportedType($mapped)) {
                return $mapped;
            }
        }

        return null;
    }

    public static function isSupportedType(string $type): bool
    {
        return in_array(strtolower($type), self::SUPPORTED_TYPES, true);
    }

    private static function normalizeSlug(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/', '', trim($value)) ?? '');
    }
}
