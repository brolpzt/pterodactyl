<?php

namespace Pterodactyl\Services\Steam;

use Pterodactyl\Models\Egg;

class EggWorkshopFeatureService
{
    public function syncForEgg(Egg $egg, bool $enabled): Egg
    {
        $features = $egg->features ?? [];

        if ($enabled) {
            if (!in_array(Egg::FEATURE_WORKSHOP, $features, true)) {
                $features[] = Egg::FEATURE_WORKSHOP;
                $egg->update(['features' => array_values($features)]);
            }

            return $egg->refresh();
        }

        if (in_array(Egg::FEATURE_WORKSHOP, $features, true)) {
            $egg->update([
                'features' => array_values(array_filter(
                    $features,
                    fn (string $feature) => $feature !== Egg::FEATURE_WORKSHOP
                )),
            ]);
        }

        return $egg->refresh();
    }

    public function isEnabled(Egg $egg): bool
    {
        return in_array(Egg::FEATURE_WORKSHOP, $egg->inherit_features ?? [], true);
    }
}
