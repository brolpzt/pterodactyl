<?php

namespace Pterodactyl\Http\Controllers\Api\Internal;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Databases\PhpMyAdminSsoService;

class PhpMyAdminSsoController extends Controller
{
    public function __construct(private PhpMyAdminSsoService $sso)
    {
    }

    /**
     * Redeem a one-time phpMyAdmin SSO token (called by the sso.php bridge).
     */
    public function redeem(Request $request): JsonResponse
    {
        $secret = (string) config('phpmyadmin.sso_secret');
        $provided = (string) ($request->bearerToken() ?: $request->header('X-PhpMyAdmin-SSO-Secret', ''));
        if ($secret === '' || !hash_equals($secret, $provided)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $token = (string) $request->input('token', '');
        $expires = (int) $request->input('expires', 0);
        $signature = (string) $request->input('signature', '');

        if ($token === '' || $expires < 1 || $signature === '') {
            return response()->json(['error' => 'invalid_request'], 422);
        }

        if ($expires < time()) {
            return response()->json(['error' => 'expired'], 403);
        }

        $expected = hash_hmac('sha256', $token . '|' . $expires, $secret);
        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'invalid_signature'], 403);
        }

        try {
            $payload = $this->sso->redeem($token);
        } catch (DisplayException $e) {
            return response()->json(['error' => 'invalid_token', 'message' => $e->getMessage()], 403);
        }

        return response()->json($payload);
    }
}
