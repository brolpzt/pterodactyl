<?php

namespace Pterodactyl\Transformers\Api\Application;

use Pterodactyl\Models\Addon;

class AddonTransformer extends BaseTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return Addon::RESOURCE_NAME;
    }

    /**
     * Transform the addon model into a representative array for the application API.
     */
    public function transform(Addon $addon): array
    {
        return [
            'id' => $addon->id,
            'uuid' => $addon->uuid,
            'egg_id' => $addon->egg_id,
            'category_id' => $addon->category_id,
            'name' => $addon->name,
            'description' => $addon->description,
            'script' => $addon->script,
            'container_image' => $addon->container_image,
            'reinstall_server' => $addon->reinstall_server,
            'is_active' => $addon->is_active,
            'created_at' => $addon->created_at->toIso8601String(),
            'updated_at' => $addon->updated_at->toIso8601String(),
        ];
    }
}
