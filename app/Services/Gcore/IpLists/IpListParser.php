<?php

namespace Pterodactyl\Services\Gcore\IpLists;

/**
 * Turns a downloaded IP list (plain text or JSON) into a sorted, aggregated list of
 * IPv4 CIDRs ready for a Gcore sip_list. IPv6 is always dropped.
 *
 * Text: one or more entries per line, separated by whitespace/commas; "#" and ";" start
 * a comment. JSON: every string value anywhere in the document that is an IP/CIDR
 * (e.g. goog.json prefixes[].ipv4Prefix); other strings are silently skipped.
 * Entries may be a.b.c.d, a.b.c.d/m or a.b.c.d-e.f.g.h.
 */
class IpListParser
{
    public function __construct(private readonly int $minPrefix = 8)
    {
    }

    public static function fromConfig(): self
    {
        return new self(max(0, min(32, (int) config('gcore.ip_lists.min_prefix', 8))));
    }

    /**
     * @return array{cidrs: list<string>, ipv6: int, invalid: int, too_broad: int}
     */
    public function parse(string $body): array
    {
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
        $trimmed = ltrim($body);
        $json = null;
        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $json = json_decode($trimmed, true);
        }

        $stats = ['ipv6' => 0, 'invalid' => 0, 'too_broad' => 0];
        $ranges = [];

        if (is_array($json)) {
            $tokens = [];
            array_walk_recursive($json, function ($value) use (&$tokens) {
                if (is_string($value)) {
                    $tokens[] = trim($value);
                }
            });
            foreach ($tokens as $token) {
                // JSON documents carry metadata strings (tokens, dates, names): only count real IPs.
                $this->addToken($token, $ranges, $stats, false);
            }
        } else {
            foreach (preg_split('/\R/', $body) ?: [] as $line) {
                $line = trim(preg_split('/[#;]/', $line, 2)[0] ?? '');
                if ($line === '') {
                    continue;
                }
                foreach (preg_split('/[\s,]+/', $line) ?: [] as $token) {
                    if ($token !== '') {
                        $this->addToken($token, $ranges, $stats, true);
                    }
                }
            }
        }

        return ['cidrs' => self::rangesToCidrs(self::mergeRanges($ranges))] + $stats;
    }

    /**
     * Aggregate already-valid IPv4 entries (used when several lists feed one rule).
     *
     * @param list<string> $entries
     * @return list<string>
     */
    public static function aggregate(array $entries): array
    {
        $ranges = [];
        foreach ($entries as $entry) {
            $range = self::parseIpv4((string) $entry);
            if ($range !== null) {
                $ranges[] = $range;
            }
        }

        return self::rangesToCidrs(self::mergeRanges($ranges));
    }

    /**
     * @param list<array{0:int,1:int}> $ranges
     * @param array{ipv6:int,invalid:int,too_broad:int} $stats
     */
    private function addToken(string $token, array &$ranges, array &$stats, bool $countInvalid): void
    {
        if ($token === '') {
            return;
        }

        if (str_contains($token, ':')) {
            $addr = explode('/', $token, 2)[0];
            if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                ++$stats['ipv6'];
            } elseif ($countInvalid) {
                ++$stats['invalid'];
            }

            return;
        }

        $range = self::parseIpv4($token);
        if ($range === null) {
            if ($countInvalid) {
                ++$stats['invalid'];
            }

            return;
        }

        // Reject anything broader than /minPrefix (0.0.0.0/0 in an allowlist = everyone).
        $size = $range[1] - $range[0] + 1;
        if ($size > (1 << (32 - $this->minPrefix))) {
            ++$stats['too_broad'];

            return;
        }

        $ranges[] = $range;
    }

    /**
     * @return array{0:int,1:int}|null inclusive [start, end] as unsigned ints
     */
    public static function parseIpv4(string $token): ?array
    {
        $token = trim($token);
        if (str_contains($token, '-')) {
            [$a, $b] = array_map('trim', explode('-', $token, 2));
            $start = self::ipToInt($a);
            $end = self::ipToInt($b);
            if ($start === null || $end === null) {
                return null;
            }

            return $start <= $end ? [$start, $end] : [$end, $start];
        }

        $parts = explode('/', $token);
        if (count($parts) > 2) {
            return null;
        }
        $ip = self::ipToInt($parts[0]);
        if ($ip === null) {
            return null;
        }
        $mask = 32;
        if (isset($parts[1])) {
            if (!ctype_digit($parts[1]) || (int) $parts[1] > 32) {
                return null;
            }
            $mask = (int) $parts[1];
        }
        $size = 1 << (32 - $mask);
        $start = $ip & ~($size - 1) & 0xFFFFFFFF;

        return [$start, $start + $size - 1];
    }

    private static function ipToInt(string $ip): ?int
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }
        $long = ip2long($ip);

        return $long === false ? null : $long & 0xFFFFFFFF;
    }

    /**
     * @param list<array{0:int,1:int}> $ranges
     * @return list<array{0:int,1:int}>
     */
    private static function mergeRanges(array $ranges): array
    {
        usort($ranges, fn ($x, $y) => $x[0] <=> $y[0] ?: $x[1] <=> $y[1]);
        $out = [];
        foreach ($ranges as $r) {
            $last = count($out) - 1;
            if ($last >= 0 && $r[0] <= $out[$last][1] + 1) {
                $out[$last][1] = max($out[$last][1], $r[1]);
            } else {
                $out[] = $r;
            }
        }

        return $out;
    }

    /**
     * Minimal CIDR cover of each range; /32 is written as the bare address.
     *
     * @param list<array{0:int,1:int}> $ranges
     * @return list<string>
     */
    private static function rangesToCidrs(array $ranges): array
    {
        $out = [];
        foreach ($ranges as [$start, $end]) {
            while ($start <= $end) {
                // Largest block aligned on $start that still fits in the range.
                $maxSize = $start === 0 ? 1 << 32 : $start & -$start;
                $remaining = $end - $start + 1;
                while ($maxSize > $remaining) {
                    $maxSize >>= 1;
                }
                $prefix = 32 - (strlen(decbin($maxSize)) - 1);
                $ip = long2ip($start);
                $out[] = $prefix === 32 ? $ip : $ip . '/' . $prefix;
                $start += $maxSize;
            }
        }

        return $out;
    }
}
