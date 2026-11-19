<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Gcore\DdosProfileService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\AntiDdos\UpdateDdosProfileRequest;

class AntiDdosProfileController extends ClientApiController
{
    public function __construct(private DdosProfileService $service)
    {
        parent::__construct();
    }

    /**
     * The server's Anti-DDoS profile and the options the client can pick.
     */
    public function index(GetServerRequest $request, Server $server): array
    {
        return ['object' => 'anti_ddos_profile', 'attributes' => $this->service->clientPayload($server)];
    }

    /**
     * Switch to another of the egg's options (cooldown and admin lock apply).
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function update(UpdateDdosProfileRequest $request, Server $server): array
    {
        $this->service->changeByClient($server, (int) $request->input('profile'), $request->user());

        return ['object' => 'anti_ddos_profile', 'attributes' => $this->service->clientPayload($server)];
    }
}
