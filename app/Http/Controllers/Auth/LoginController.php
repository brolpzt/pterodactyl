<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

class LoginController extends AbstractLoginController
{
    /**
     * @deprecated Direct panel login is disabled.
     */
    public function index(): View
    {
        return view('templates/auth.core');
    }

    public function login(Request $request): JsonResponse
    {
        abort(403, 'Direct login is disabled.');
    }
}
