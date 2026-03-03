<?php

namespace Pterodactyl\Listeners\Server;

use Pterodactyl\Events\Server\Installed;
use Pterodactyl\Services\Servers\FastDlSyncService;

class FastDlSyncListener
{
    /**
     * FastDlSyncListener constructor.
     */
    public function __construct(protected FastDlSyncService $syncService)
    {
    }

    /**
     * Handle the event.
     */
    public function handle(Installed $event): void
    {
        try {
            $this->syncService->handle($event->server);
        } catch (\Exception $exception) {
            // Log error but don't fail the installation process
            \Log::error('Failed to trigger auto FastDL sync: ' . $exception->getMessage(), [
                'server_id' => $event->server->id,
            ]);
        }
    }
}
