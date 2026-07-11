<?php

namespace Pterodactyl\Services\Steam;

use Pterodactyl\Models\Egg;

class EggWorkshopFeatureService
{
    public function syncForEgg(Egg $egg, bool $enabled, ?int $workshopAppId = null, ?string $workshopSyncDriver = null): Egg
    {
        $features = $egg->features ?? [];
        $updates = [
            'workshop_app_id' => $workshopAppId ?: null,
            'workshop_sync_driver' => $workshopSyncDriver ?: null,
        ];

        if ($enabled) {
            if (!in_array(Egg::FEATURE_WORKSHOP, $features, true)) {
                $features[] = Egg::FEATURE_WORKSHOP;
            }
            $updates['features'] = array_values($features);
            $egg->update($updates);

            return $egg->refresh();
        }

        if (in_array(Egg::FEATURE_WORKSHOP, $features, true)) {
            $updates['features'] = array_values(array_filter(
                $features,
                fn (string $feature) => $feature !== Egg::FEATURE_WORKSHOP
            ));
        }

        $egg->update($updates);

        return $egg->refresh();
    }

    public function isEnabled(Egg $egg): bool
    {
        return in_array(Egg::FEATURE_WORKSHOP, $egg->inherit_features ?? [], true);
    }

    public function resolveWorkshopAppId(Egg $egg): ?int
    {
        if (!empty($egg->workshop_app_id)) {
            return (int) $egg->workshop_app_id;
        }

        if ($egg->config_from) {
            $egg->loadMissing('configFrom');
        }

        if ($egg->configFrom?->workshop_app_id) {
            return (int) $egg->configFrom->workshop_app_id;
        }

        return null;
    }

    public function resolveWorkshopSyncDriver(Egg $egg): ?string
    {
        if (!empty($egg->workshop_sync_driver)) {
            return (string) $egg->workshop_sync_driver;
        }

        if ($egg->config_from) {
            $egg->loadMissing('configFrom');
        }

        if ($egg->configFrom?->workshop_sync_driver) {
            return (string) $egg->configFrom->workshop_sync_driver;
        }

        return null;
    }
}
