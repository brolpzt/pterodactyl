<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Models\DeployPlan;

class DeployPlanFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return DeployPlan::$validationRules;
    }
}
