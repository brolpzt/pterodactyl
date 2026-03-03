<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Addon;
use Pterodactyl\Models\Server;
use Pterodactyl\Transformers\Api\Client\AddonTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Addons\GetAddonsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Addons\InstallAddonRequest;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

class AddonController extends ClientApiController
{
    /**
     * AddonController constructor.
     */
    public function __construct(private DaemonServerRepository $daemonServerRepository)
    {
        parent::__construct();
    }

    /**
     * Returns all addons available for the server.
     */
    public function index(GetAddonsRequest $request, Server $server): array
    {
        $addons = Addon::where('egg_id', $server->egg_id)
            ->where('is_active', true)
            ->get();

        return $this->fractal->collection($addons)
            ->transformWith($this->getTransformer(AddonTransformer::class))
            ->toArray();
    }

    /**
     * Triggers the installation of an addon.
     */
    public function install(InstallAddonRequest $request, Server $server, string $addonUuid): array
    {
        $addon = Addon::where('uuid', $addonUuid)->firstOrFail();

        // Validate that this addon belongs to the server's egg
        if ($addon->egg_id !== $server->egg_id || !$addon->is_active) {
            abort(404);
        }

        $this->daemonServerRepository->setServer($server)->executeAddon([
            'script' => $addon->script,
            'container_image' => $addon->container_image,
        ]);

        // Mark as installed in the pivot table
        $server->addons()->syncWithoutDetaching([$addon->id => ['installed_at' => now()]]);

        return [
            'success' => true,
        ];
    }
}
