<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\AuthManager;
use Illuminate\Container\Container;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Events\Auth\DirectLogin;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Auth\SsoLoginService;
use Pterodactyl\Exceptions\Auth\SsoAuthenticationException;

class SsoLoginController extends Controller
{
    public function __construct(private SsoLoginService $ssoLoginService)
    {
    }

    /**
     * Authenticate a user via a signed SSO link from an external control panel.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if ($allowedIps = config('sso.allowed_ips', [])) {
            $clientIp = $request->ip();
            if (!in_array($clientIp, $allowedIps, true)) {
                $this->logFailure($request, 'IP not allowed');

                abort(403, 'Invalid or expired login link.');
            }
        }

        try {
            [$user, $server] = $this->ssoLoginService->authenticate($request->query());
        } catch (SsoAuthenticationException $exception) {
            $this->logFailure($request, $exception->getMessage());

            Event::dispatch(new Failed('auth', null, ['sso' => true]));

            abort(403, 'Invalid or expired login link.');
        }

        $auth = Container::getInstance()->make(AuthManager::class);

        if ($auth->guard()->check() && $auth->guard()->id() !== $user->id) {
            $auth->guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->session()->remove('auth_confirmation_token');
        $request->session()->regenerate();

        $auth->guard()->login($user, true);

        Event::dispatch(new DirectLogin($user, true));

        Activity::event('auth:sso')
            ->withRequestMetadata()
            ->subject($user)
            ->property('server', $server->uuidShort)
            ->log();

        return redirect('/server/' . $server->uuidShort);
    }

    private function logFailure(Request $request, string $reason): void
    {
        Activity::event('auth:sso-fail')
            ->withRequestMetadata()
            ->property('reason', $reason)
            ->property('user', $request->query('user'))
            ->property('server', $request->query('server'))
            ->log();
    }
}
