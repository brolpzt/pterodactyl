<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Steam\WorkshopService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Workshop\BrowseWorkshopRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Workshop\GetWorkshopItemRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Workshop\ListInstalledWorkshopRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Workshop\InstallWorkshopItemRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Workshop\UninstallWorkshopItemRequest;

class WorkshopController extends ClientApiController
{
    public function __construct(private WorkshopService $workshopService)
    {
        parent::__construct();
    }

    public function browse(BrowseWorkshopRequest $request, Server $server): array
    {
        $result = $this->workshopService->browse(
            $server,
            (string) $request->input('q', ''),
            (string) $request->input('sort', 'trending'),
            $request->input('cursor'),
            (int) $request->input('per_page', 30)
        );

        return [
            'object' => 'workshop_browse',
            'attributes' => $result,
        ];
    }

    public function show(GetWorkshopItemRequest $request, Server $server, string $fileId): array
    {
        return [
            'object' => 'workshop_item',
            'attributes' => $this->workshopService->itemDetails($server, (int) $fileId),
        ];
    }

    public function installed(ListInstalledWorkshopRequest $request, Server $server): array
    {
        return [
            'object' => 'list',
            'data' => array_map(
                fn (array $item) => ['object' => 'workshop_installed_item', 'attributes' => $item],
                $this->workshopService->installed($server)
            ),
        ];
    }

    public function install(InstallWorkshopItemRequest $request, Server $server): array
    {
        $publishedFileId = (int) $request->input('published_file_id');

        $result = $this->workshopService->install($server, $publishedFileId);

        Activity::event('server:workshop.install')
            ->property('published_file_id', $publishedFileId)
            ->log();

        return [
            'object' => 'workshop_installed_item',
            'attributes' => $result,
        ];
    }

    public function uninstall(UninstallWorkshopItemRequest $request, Server $server, string $fileId): array
    {
        $this->workshopService->uninstall($server, (int) $fileId);

        Activity::event('server:workshop.uninstall')
            ->property('published_file_id', (int) $fileId)
            ->log();

        return ['object' => 'workshop_uninstall', 'attributes' => ['success' => true]];
    }
}
