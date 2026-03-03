<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Addon;

class AddonTransformer extends BaseClientTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return Addon::RESOURCE_NAME;
    }

    /**
     * Transform the addon model into a representative array for the client API.
     */
    public function transform(Addon $addon): array
    {
        return [
            'id' => $addon->id,
            'uuid' => $addon->uuid,
            'name' => $addon->name,
            'description' => $addon->description,
            'container_image' => $addon->container_image,
            'is_active' => $addon->is_active,
            'created_at' => $addon->created_at->toIso8601String(),
            'updated_at' => $addon->updated_at->toIso8601String(),
        ];
    }
}
