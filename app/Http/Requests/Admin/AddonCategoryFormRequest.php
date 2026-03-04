<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Models\AddonCategory;

class AddonCategoryFormRequest extends AdminFormRequest
{
    /**
     * Return the rules for this request.
     */
    public function rules(): array
    {
        return AddonCategory::$validationRules;
    }
}
