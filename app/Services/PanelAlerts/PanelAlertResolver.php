<?php

namespace Pterodactyl\Services\PanelAlerts;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;
use Pterodactyl\Models\PanelAlert;

/**
 * Decides which alerts a user gets to see on each part of the panel.
 */
class PanelAlertResolver
{
    /**
     * Alerts for pages that do not belong to a server. An alert aimed at specific
     * servers is shown when the user has access to at least one of them.
     *
     * @return Collection<int, PanelAlert>
     */
    public function forAccount(User $user): Collection
    {
        $servers = null;

        return $this->active($user, PanelAlert::PLACEMENT_DASHBOARD, PanelAlert::PLACEMENT_ACCOUNT)
            ->filter(function (PanelAlert $alert) use ($user, &$servers) {
                if (!$alert->isTargeted()) {
                    return true;
                }

                $servers ??= $user->accessibleServers()->with('node:id,location_id')->get();

                return $servers->contains(fn (Server $server) => $alert->matchesServer($server));
            })
            ->values();
    }

    /**
     * @return Collection<int, PanelAlert>
     */
    public function forServer(User $user, Server $server): Collection
    {
        return $this->active($user, PanelAlert::PLACEMENT_SERVER)
            ->filter(fn (PanelAlert $alert) => !$alert->isTargeted() || $alert->matchesServer($server))
            ->values();
    }

    /**
     * Server filters do not apply here: administrators manage every server.
     *
     * @return Collection<int, PanelAlert>
     */
    public function forAdmin(User $user): Collection
    {
        return $this->active($user, PanelAlert::PLACEMENT_ADMIN);
    }

    /**
     * @return Collection<int, PanelAlert>
     */
    private function active(User $user, string ...$placements): Collection
    {
        return PanelAlert::query()
            ->active()
            ->orderByDesc('id')
            ->get()
            ->filter(fn (PanelAlert $alert) => $alert->isPlacedOn(...$placements) && $alert->isVisibleTo($user))
            ->values()
            ->toBase();
    }
}
