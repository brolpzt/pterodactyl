<?php

namespace Pterodactyl\Services\Cloudflare;

use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggDnsProfile;

class EggDnsProfileService
{
    public function syncForEgg(Egg $egg, array $data): EggDnsProfile
    {
        $allowedTypes = array_values(array_unique(array_filter($data['allowed_types'] ?? [EggDnsProfile::TYPE_A])));
        if (empty($allowedTypes)) {
            $allowedTypes = [EggDnsProfile::TYPE_A];
        }

        $defaultType = strtoupper($data['default_type'] ?? EggDnsProfile::TYPE_A);
        if (!in_array($defaultType, $allowedTypes, true)) {
            $defaultType = $allowedTypes[0];
        }

        return EggDnsProfile::updateOrCreate(
            ['egg_id' => $egg->id],
            [
                'enabled' => (bool) ($data['enabled'] ?? false),
                'allowed_types' => $allowedTypes,
                'default_type' => $defaultType,
                'max_records_per_server' => (int) ($data['max_records_per_server'] ?? 3),
            ]
        );
    }
}
