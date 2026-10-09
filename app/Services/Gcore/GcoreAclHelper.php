<?php

namespace Pterodactyl\Services\Gcore;

class GcoreAclHelper
{
    /** @return list<int> */
    public static function parsePortList(string $input): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', trim($input)) ?: [] as $part) {
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $p = (int) $part;
            if ($p >= 1 && $p <= 65535) {
                $out[] = $p;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    public static function parseIpList(string $input): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', trim($input)) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return list<string> */
    public static function parseProtoList(string $input): array
    {
        $allowed = GcoreClient::PROTOCOLS;
        $out = [];
        foreach (preg_split('/[\s,;]+/', strtolower(trim($input))) ?: [] as $part) {
            if (in_array($part, $allowed, true)) {
                $out[] = $part;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param array<string, mixed> $profile
     * @return array{rate: array{low:int,medium:int,high:int,geo:int}, geoip: list<string>, acl: list<array<string, mixed>>}
     */
    public static function extractProfileFormData(array $profile): array
    {
        $rate = ['low' => 50, 'medium' => 150, 'high' => 300, 'geo' => 300];
        $geoip = [];
        $acl = [];

        foreach ($profile['fields'] ?? [] as $field) {
            $name = $field['name'] ?? '';
            $val = $field['field_value'] ?? null;
            match ($name) {
                'Rate limiter low' => $rate['low'] = (int) $val,
                'Rate limiter medium' => $rate['medium'] = (int) $val,
                'Rate limiter high' => $rate['high'] = (int) $val,
                'Rate limiter geo' => $rate['geo'] = (int) $val,
                'GEOIP list' => $geoip = is_array($val) ? $val : [],
                'ACL list' => $acl = is_array($val) ? $val : [],
                default => null,
            };
        }

        return compact('rate', 'geoip', 'acl');
    }

    /**
     * @param array<string, mixed> $post
     * @return list<array<string, mixed>>
     */
    public static function aclFromPost(array $post): array
    {
        $rules = $post['acl'] ?? [];
        if (!is_array($rules)) {
            return [];
        }

        $out = [];
        foreach ($rules as $row) {
            if (!is_array($row)) {
                continue;
            }
            $policy = trim((string) ($row['policy'] ?? ''));
            if ($policy === '') {
                continue;
            }
            $out[] = [
                'policy' => $policy,
                'sip_list' => self::parseIpList((string) ($row['sip_list'] ?? '')),
                'dport_list' => self::parsePortList((string) ($row['dport_list'] ?? '')),
                'proto_list' => self::parseProtoList((string) ($row['proto_list'] ?? '')),
                'sport_list' => self::parsePortList((string) ($row['sport_list'] ?? '')),
            ];
        }

        return $out;
    }
}
