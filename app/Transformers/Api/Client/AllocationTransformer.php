<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Allocation;

class AllocationTransformer extends BaseClientTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return 'allocation';
    }

    public function transform(Allocation $model): array
    {
        $portEnv = $model->port_env ? strtoupper((string) $model->port_env) : null;
        $slotName = null;
        if ($portEnv && $model->relationLoaded('server') && $model->server) {
            $model->server->loadMissing('egg');
            foreach ($model->server->egg->port_slots ?? [] as $slot) {
                if (strtoupper((string) ($slot['env_variable'] ?? '')) === $portEnv) {
                    $slotName = (string) ($slot['name'] ?? $portEnv);
                    break;
                }
            }
        }

        return [
            'id' => $model->id,
            'ip' => $model->ip,
            'ip_alias' => $model->ip_alias,
            'port' => $model->port,
            'notes' => $model->notes,
            'port_env' => $portEnv,
            'port_slot_name' => $slotName,
            'is_default' => $model->server->allocation_id === $model->id,
        ];
    }
}
