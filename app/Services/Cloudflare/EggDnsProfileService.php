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

        $srvService = $this->normalizeSrvLabel($data['srv_service'] ?? '_minecraft');
        $srvProtocol = $this->normalizeSrvLabel($data['srv_protocol'] ?? '_tcp');

        return EggDnsProfile::updateOrCreate(
            ['egg_id' => $egg->id],
            [
                'enabled' => (bool) ($data['enabled'] ?? false),
                'allowed_types' => $allowedTypes,
                'default_type' => $defaultType,
                'max_records_per_server' => (int) ($data['max_records_per_server'] ?? 3),
                'srv_service' => $srvService,
                'srv_protocol' => $srvProtocol,
                'srv_priority' => (int) ($data['srv_priority'] ?? 0),
                'srv_weight' => (int) ($data['srv_weight'] ?? 5),
            ]
        );
    }

    private function normalizeSrvLabel(string $value): string
    {
        $value = trim($value);

        return str_starts_with($value, '_') ? $value : "_{$value}";
    }
}
