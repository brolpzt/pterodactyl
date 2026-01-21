<?php

namespace Pterodactyl\Transformers\Api\Application;

use Pterodactyl\Models\EggVariable;

class EggVariableTransformer extends BaseTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return EggVariable::RESOURCE_NAME;
    }

    public function transform(EggVariable $variable): array
    {
        return [
            'name' => $variable->name,
            'description' => $variable->description,
            'env_variable' => $variable->env_variable,
            'default_value' => $variable->default_value,
            'server_value' => $variable->server_value ?? null,
            'is_editable' => $variable->user_editable,
            'is_viewable' => $variable->user_viewable,
            'rules' => $variable->rules,
        ];
    }
}
