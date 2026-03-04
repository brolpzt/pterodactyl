<?php

namespace Pterodactyl\Transformers\Api\Application;

use Pterodactyl\Models\AddonCategory;

class AddonCategoryTransformer extends BaseTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return AddonCategory::RESOURCE_NAME;
    }

    /**
     * Transform the model into a representative array for the application API.
     */
    public function transform(AddonCategory $category): array
    {
        return [
            'id' => $category->id,
            'egg_id' => $category->egg_id,
            'name' => $category->name,
            'description' => $category->description,
            'created_at' => $category->created_at->toIso8601String(),
            'updated_at' => $category->updated_at->toIso8601String(),
        ];
    }
}
