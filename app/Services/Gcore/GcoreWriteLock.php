<?php

namespace Pterodactyl\Services\Gcore;

use Illuminate\Support\Facades\Cache;

/**
 * One Gcore profile write at a time across the whole panel (port syncs, IP-list refreshes,
 * the profile editor, queue workers and the scheduler alike). Re-entrant within a process,
 * so a job holding it can call code that asks for it again.
 */
final class GcoreWriteLock
{
    public const KEY = 'gcore:write';

    /** Longer than any job timeout: a killed worker frees it on expiry. */
    public const TTL = 180;

    private static int $depth = 0;

    /**
     * Wait up to $wait seconds for the lock, then run.
     *
     * @throws \Illuminate\Contracts\Cache\LockTimeoutException
     */
    public static function run(callable $callback, int $wait = 60): mixed
    {
        if (self::$depth > 0) {
            return $callback();
        }

        $lock = Cache::lock(self::KEY, self::TTL);
        $lock->block($wait);

        return self::holding($lock, $callback);
    }

    /**
     * Run only if the lock is free right now (queue jobs: when busy they go back to the queue).
     *
     * @return array{0: bool, 1: mixed} [ran, result]
     */
    public static function attempt(callable $callback): array
    {
        if (self::$depth > 0) {
            return [true, $callback()];
        }

        $lock = Cache::lock(self::KEY, self::TTL);
        if (!$lock->get()) {
            return [false, null];
        }

        return [true, self::holding($lock, $callback)];
    }

    private static function holding(\Illuminate\Contracts\Cache\Lock $lock, callable $callback): mixed
    {
        ++self::$depth;
        try {
            return $callback();
        } finally {
            --self::$depth;
            $lock->release();
        }
    }
}
