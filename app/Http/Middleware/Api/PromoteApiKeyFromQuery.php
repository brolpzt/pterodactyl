<?php

namespace Pterodactyl\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;

/**
 * Copies an Application/Client API token from ?key= or ?api_key= into the
 * Authorization Bearer header when no Bearer token is already present.
 *
 * Useful for external monitors that cannot set custom HTTP headers.
 */
class PromoteApiKeyFromQuery
{
    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->bearerToken()) {
            return $next($request);
        }

        $token = trim((string) ($request->query('key') ?? $request->query('api_key') ?? ''));
        if ($token !== '') {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
