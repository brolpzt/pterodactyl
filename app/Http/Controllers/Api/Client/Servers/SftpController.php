<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Services\Users\SftpPasswordService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\SftpPasswordRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SftpController extends ClientApiController
{
    public function __construct(private SftpPasswordService $passwordService)
    {
        parent::__construct();
    }

    /**
     * Return whether the authenticated user has an SFTP password configured.
     */
    public function index(SftpPasswordRequest $request, Server $server): JsonResponse
    {
        $user = $request->user()->refresh();

        return new JsonResponse([
            'object' => 'sftp_password',
            'attributes' => [
                'has_password' => $this->passwordService->hasPassword($user),
            ],
        ]);
    }

    /**
     * Reveal the authenticated user's SFTP password.
     */
    public function reveal(SftpPasswordRequest $request, Server $server): JsonResponse
    {
        $user = $request->user()->refresh();

        try {
            $password = $this->passwordService->reveal($user);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            throw new NotFoundHttpException('No SFTP password is configured for this account.');
        }

        Activity::event('user:account.sftp-password-reveal')->log();

        return new JsonResponse([
            'object' => 'sftp_password',
            'attributes' => [
                'has_password' => true,
                'password' => $password,
            ],
        ]);
    }

    /**
     * Generate or rotate the authenticated user's SFTP password.
     */
    public function rotate(SftpPasswordRequest $request, Server $server): JsonResponse
    {
        $password = Activity::event('user:account.sftp-password-rotate')
            ->transaction(fn () => $this->passwordService->rotate($request->user()));

        return new JsonResponse([
            'object' => 'sftp_password',
            'attributes' => [
                'has_password' => true,
                'password' => $password,
            ],
        ]);
    }
}
