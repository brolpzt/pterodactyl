<?php

namespace Pterodactyl\Support;

use Pterodactyl\Models\Server;

class MonitorProtocolResolver
{
    public const PROTOCOL_SOURCE = 'source';
    public const PROTOCOL_QUAKE3 = 'quake3';
    public const PROTOCOL_SAMP = 'samp';
    public const PROTOCOL_TEAMSPEAK = 'teamspeak';

    private const DEFAULT_TS3_QUERY_PORT = 10011;

    /**
     * Tipos gamedig → protocolo quake3 (Call of Duty / Quake 3 family).
     *
     * @var array<int, string>
     */
    private const QUAKE3_TYPES = [
        'cod', 'cod-mw2', 'cod-waw', 'cod2', 'cod4', 'cod4x', 'codmw3', 'coduo',
        'et', 'iw4x', 'mohaa', 'mw2', 'mw3', 'ql', 'quake-live', 'quake3',
        'waw', 'wolfenstein-et',
    ];

    /**
     * Tipos gamedig → protocolo samp.
     *
     * @var array<int, string>
     */
    private const SAMP_TYPES = [
        'gtasa', 'samp',
    ];

    /**
     * Tipos gamedig → protocolo source (A2S / Source / GoldSrc e afins).
     *
     * @var array<int, string>
     */
    private const SOURCE_TYPES = [
        '7-days-to-die', '7d2d', '7dtd', 'alien-swarm-reactive-drop', 'ark',
        'ark-survival-ascended', 'ark-survival-evolved', 'arma-2', 'arma-3',
        'arma-reforger', 'arma2', 'arma3', 'asa', 'ase', 'asrd', 'atlas',
        'barotrauma', 'battalion', 'battalion-1944', 'bb2', 'beyond-the-wire',
        'black-mesa', 'bm', 'bms', 'brainbread-2', 'btw', 'conan', 'conan-exiles',
        'counter-strike', 'counter-strike-2', 'counter-strike-go',
        'counter-strike-source', 'counterstrike16', 'cs', 'cs16', 'cs2', 'cscz',
        'csgo', 'css', 'cstrike', 'cz', 'dark-and-light', 'day-of-defeat',
        'day-of-defeat-source', 'day-of-infamy', 'dayz', 'deathmatch-classic',
        'dmc', 'dnl', 'dod', 'dods', 'doi', 'empyrion', 'enshrouded',
        'fistful-of-frags', 'fof', 'forest', 'garrys-mod', 'gmod', 'goldsource',
        'half-life', 'half-life-2-deathmatch', 'half-life-deathmatch-source',
        'half-life-opposing-force', 'hell-let-loose', 'hl', 'hl1', 'hl2dm',
        'hldms', 'hll', 'hlof', 'holdfast', 'holdfast-naw', 'hurtworld', 'ins',
        'inss', 'insurgency', 'insurgency-sandstorm', 'isle', 'kf2',
        'killing-floor-2', 'l4d', 'l4d2', 'left-4-dead', 'left-4-dead-2',
        'miscreated', 'moe', 'mordhau', 'myth-of-empires', 'natural-selection-2',
        'nd', 'neotokyo', 'nmrih', 'no-more-room-in-hell', 'ns2', 'nt',
        'nuclear-dawn', 'opfor', 'palworld', 'pirates-vikings-and-knights-ii',
        'pixark', 'project-zomboid', 'pvkii', 'pw', 'pz', 'red-orchestra-2',
        'reforger', 'ricochet', 'rising-storm-2', 'ro2', 'rs2', 'rust', 's44',
        'se', 'sons-of-the-forest', 'sotf', 'soulmask', 'space-engineers',
        'squad', 'squad-44', 'starbound', 'sven-coop', 'svencoop', 't4',
        'team-fortress-2', 'team-fortress-classic', 'tf2', 'tfc', 'the-forest',
        'the-front', 'the-isle', 'unturned', 'v-rising', 'valheim', 'vrising',
        'wet', 'zombie-panic-source', 'zomboid', 'zps',
    ];

    /**
     * Resolve o protocolo de monitorização, ou null se o servidor deve ser omitido.
     */
    public static function resolve(Server $server): ?string
    {
        if (ServerType::isTs3($server)) {
            return self::PROTOCOL_TEAMSPEAK;
        }

        $server->loadMissing('egg');

        $type = GameDigTypeResolver::resolve(
            $server->egg?->gamedig,
            $server->egg?->name
        );

        if ($type === null) {
            return null;
        }

        $normalized = strtolower($type);

        if (in_array($normalized, self::SAMP_TYPES, true)) {
            return self::PROTOCOL_SAMP;
        }

        if (in_array($normalized, self::QUAKE3_TYPES, true)) {
            return self::PROTOCOL_QUAKE3;
        }

        if (in_array($normalized, self::SOURCE_TYPES, true)) {
            return self::PROTOCOL_SOURCE;
        }

        return null;
    }

    /**
     * Monta o address no formato esperado pelo monitor.
     * Teamspeak: IP:voz:query — restantes: IP:porta.
     */
    public static function address(Server $server, string $protocol): ?string
    {
        $server->loadMissing(['allocation', 'node']);

        $allocation = $server->allocation;
        if ($allocation === null) {
            return null;
        }

        $host = self::resolveHost($server);
        if ($host === '') {
            return null;
        }

        if ($protocol === self::PROTOCOL_TEAMSPEAK) {
            [$voicePort, $queryPort] = self::resolveTs3Ports($server);

            return sprintf('%s:%d:%d', $host, $voicePort, $queryPort);
        }

        return sprintf('%s:%d', $host, (int) $allocation->port);
    }

    /**
     * @return array{address: string, protocol: string}|null
     */
    public static function toMonitorEntry(Server $server): ?array
    {
        $protocol = self::resolve($server);
        if ($protocol === null) {
            return null;
        }

        $address = self::address($server, $protocol);
        if ($address === null) {
            return null;
        }

        return [
            'address' => $address,
            'protocol' => $protocol,
        ];
    }

    private static function resolveHost(Server $server): string
    {
        $allocation = $server->allocation;
        $host = trim((string) ($allocation?->alias ?? ''));

        if (in_array($host, ['', '0.0.0.0', '::', '::0'], true)) {
            $host = trim((string) ($server->node?->fqdn ?? ''));
        }

        return $host;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function resolveTs3Ports(Server $server): array
    {
        $server->loadMissing('variables');

        $variables = [];
        foreach ($server->variables as $variable) {
            $key = strtoupper(trim((string) $variable->env_variable));
            $override = $variable->server_value;
            $value = (is_null($override) || $override === '')
                ? $variable->default_value
                : $override;
            $variables[$key] = is_string($value) ? trim($value) : $value;
        }

        $voicePort = (int) ($variables['TS3_SERVER_PORT']
            ?? $variables['SERVER_PORT']
            ?? $server->allocation?->port
            ?? 9987);

        $queryPort = (int) ($variables['TS3_QUERY_PORT']
            ?? $variables['QUERY_PORT']
            ?? self::DEFAULT_TS3_QUERY_PORT);

        return [$voicePort, $queryPort];
    }
}
