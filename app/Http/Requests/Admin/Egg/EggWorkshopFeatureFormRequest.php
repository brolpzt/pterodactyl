<?php

namespace Pterodactyl\Http\Requests\Admin\Egg;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class EggWorkshopFeatureFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => 'sometimes|boolean',
        ];
    }

    public function validated($key = null, $default = null): array
    {
        parent::validated();

        return [
            'enabled' => $this->boolean('enabled'),
        ];
    }
}
