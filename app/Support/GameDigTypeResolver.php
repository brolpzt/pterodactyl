<?php

namespace Pterodactyl\Support;

use GameQ\Server as GameQServer;
use GameQ\Exception\Server as GameQServerException;

class GameDigTypeResolver
{
    /**
     * Aliases comuns do campo gamedig → tipo GameQ (protocolo).
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
        try {
            new GameQServer([
                'type' => $type,
                'host' => '127.0.0.1:27015',
            ]);

            return true;
        } catch (GameQServerException) {
            return false;
        }
    }

    private static function normalizeSlug(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/', '', trim($value)) ?? '');
    }
}
