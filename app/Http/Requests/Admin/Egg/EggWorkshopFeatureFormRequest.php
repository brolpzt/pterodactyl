<?php

namespace Pterodactyl\Http\Requests\Admin\Egg;

use Illuminate\Validation\Rule;
use Pterodactyl\Models\Egg;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class EggWorkshopFeatureFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => 'sometimes|boolean',
            'workshop_app_id' => 'nullable|integer|min:1',
            'workshop_sync_driver' => [
                'nullable',
                'string',
                Rule::in([
                    Egg::WORKSHOP_SYNC_BROWSE_ONLY,
                    Egg::WORKSHOP_SYNC_GMOD_LUA,
                    Egg::WORKSHOP_SYNC_L4D2_VPK,
                    Egg::WORKSHOP_SYNC_ARK_INI,
                    Egg::WORKSHOP_SYNC_SOURCE_COLLECTION,
                ]),
            ],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        parent::validated();

        return [
            'enabled' => $this->boolean('enabled'),
            'workshop_app_id' => $this->filled('workshop_app_id') ? (int) $this->input('workshop_app_id') : null,
            'workshop_sync_driver' => $this->filled('workshop_sync_driver') ? (string) $this->input('workshop_sync_driver') : null,
        ];
    }
}
