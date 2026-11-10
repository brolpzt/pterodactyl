<?php

namespace Pterodactyl\Services\Gcore;

/**
 * Gcore answered with an HTTP error. $status lets callers tell a rejected request (4xx) from
 * one whose outcome is unknown (5xx/gateway timeout: the change may have been applied anyway).
 */
class GcoreApiException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    /** True when Gcore may have applied the request even though it reported an error. */
    public static function outcomeUnknown(\Throwable $e): bool
    {
        if ($e instanceof self) {
            return $e->status >= 500;
        }

        return $e instanceof \Illuminate\Http\Client\ConnectionException;
    }
}
