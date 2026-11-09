<?php

namespace Pterodactyl\Http\Controllers\Api\HgHlds;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\HgHlds\ActivationService;

class ActivationController extends Controller
{
    public function __construct(private ActivationService $service)
    {
    }

    /**
     * Public and stateless: the source address decides who may get a license, and it
     * is the engine that checks the signed nonce matches the one it sent.
     */
    public function __invoke(Request $request): Response
    {
        $ip = $this->service->clientIp($request);
        if ($ip === null || !$this->service->isAllowed($ip)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        if (preg_match('/\Anonce=([0-9a-f]{64})\s*\z/', $request->getContent(), $matches) !== 1) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $license = $this->service->sign($matches[1]);
        if ($license === null) {
            return new Response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new Response($license, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
}
