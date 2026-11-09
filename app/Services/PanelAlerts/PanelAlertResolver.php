<?php

namespace Pterodactyl\Services\PanelAlerts;

use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;
use Pterodactyl\Models\PanelAlert;
use Pterodactyl\Services\HgHlds\EngineDetector;

/**
 * Decides which alerts a user gets to see on each part of the panel.
 */
class PanelAlertResolver
{
    public function __construct(private EngineDetector $engineDetector)
    {
    }

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
            ->whereNull('server_condition')
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
            ->filter(fn (PanelAlert $alert) => $this->meetsCondition($alert, $server))
            ->values();
    }

    /**
     * Server filters do not apply here: administrators manage every server.
     *
     * @return Collection<int, PanelAlert>
     */
    public function forAdmin(User $user): Collection
    {
        return $this->active($user, PanelAlert::PLACEMENT_ADMIN)->whereNull('server_condition')->values();
    }

    /**
     * Runs after every other filter, as a condition may have to ask the node.
     */
    private function meetsCondition(PanelAlert $alert, Server $server): bool
    {
        return match ($alert->server_condition) {
            null => true,
            PanelAlert::CONDITION_HGHLDS_MISSING => $this->engineDetector->isInstalled($server) === false,
            default => false,
        };
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
