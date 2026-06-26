<?php

namespace Pterodactyl\Http\Controllers\Admin\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Pterodactyl\Http\Controllers\Auth\AbstractLoginController;

class AdminLoginController extends AbstractLoginController
{
    protected string $redirectTo = '/admin';

    public function index(): View
    {
        return view('admin.auth.login', [
            'checkpoint' => false,
        ]);
    }

    public function checkpoint(Request $request): View|RedirectResponse
    {
        $details = $request->session()->get('auth_confirmation_token');
        if (!$details || empty($details['token_value'])) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.login', [
            'checkpoint' => true,
            'confirmationToken' => $details['token_value'],
        ]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        try {
            $username = $request->input('user');

            /** @var User $user */
            $user = User::query()->where($this->getField($username), $username)->firstOrFail();
        } catch (ModelNotFoundException) {
            $this->sendFailedLoginResponse($request);
        }

        if (!$user->root_admin) {
            $this->sendFailedLoginResponse($request);
        }

        if (!password_verify($request->input('password'), $user->password)) {
            $this->sendFailedLoginResponse($request, $user);
        }

        if (!$user->use_totp) {
            return $this->sendLoginResponse($user, $request);
        }

        Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

        $request->session()->put('auth_confirmation_token', [
            'user_id' => $user->id,
            'token_value' => $token = Str::random(64),
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);

        return new JsonResponse([
            'data' => [
                'complete' => false,
                'confirmation_token' => $token,
            ],
        ]);
    }
}
