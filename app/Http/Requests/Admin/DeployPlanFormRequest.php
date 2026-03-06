<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Models\DeployPlan;

class DeployPlanFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $rules = DeployPlan::$validationRules;

        $rules['variable_overrides'] = 'sometimes|array';
        $rules['variable_overrides.*'] = 'nullable|string';

        return $rules;
    }
}
