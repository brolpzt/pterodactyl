<?php

namespace Pterodactyl\Http\Middleware\Api\Client\Server;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Support\ServerType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DenyConsoleForTs3
{
    public function handle(Request $request, Closure $next): mixed
    {
        $server = $request->route()->parameter('server');
        $user = $request->user();

        if ($server instanceof Server && ServerType::isTs3($server) && !($user?->root_admin ?? false)) {
            throw new AccessDeniedHttpException('Console access is disabled for TeamSpeak 3 servers.');
        }

        return $next($request);
    }
}
