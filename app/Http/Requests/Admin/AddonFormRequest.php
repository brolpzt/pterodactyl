<?php

namespace Pterodactyl\Http\Requests\Admin;

use Pterodactyl\Models\Addon;

class AddonFormRequest extends AdminFormRequest
{
    /**
     * Return the rules for this request.
     */
    public function rules(): array
    {
        return Addon::$validationRules;
    }
}
