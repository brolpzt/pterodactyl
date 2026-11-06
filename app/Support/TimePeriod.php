<?php

namespace Pterodactyl\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class TimePeriod
{
    /**
     * @var array<string, int>
     */
    public const DURATIONS = [
        '15m' => 15 * 60,
        '1h' => 60 * 60,
        '6h' => 6 * 60 * 60,
        '24h' => 24 * 60 * 60,
        '7d' => 7 * 24 * 60 * 60,
        '30d' => 30 * 24 * 60 * 60,
        '90d' => 90 * 24 * 60 * 60,
    ];

    /**
     * Normalize a period query value against an allow-list.
     *
     * @param list<string> $allowed
     */
    public static function normalize(?string $period, array $allowed, string $default): string
    {
        $value = strtolower(trim((string) $period));
        if ($value !== '' && in_array($value, $allowed, true) && isset(self::DURATIONS[$value])) {
            return $value;
        }

        return $default;
    }

    public static function since(string $period, ?Carbon $now = null): Carbon
    {
        if (!isset(self::DURATIONS[$period])) {
            throw new InvalidArgumentException("Unsupported period [$period]");
        }

        return ($now ?? Carbon::now())->copy()->subSeconds(self::DURATIONS[$period]);
    }

    public static function seconds(string $period): int
    {
        return self::DURATIONS[$period] ?? 0;
    }
}
