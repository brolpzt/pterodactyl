<?php

namespace Pterodactyl\Http\Requests\Admin\Egg;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class EggFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'docker_images' => ['required', 'string', 'regex:/^[\w#\.\/\- ]*\|?~?[\w\.\/\-:@ ]*$/im'],
            'force_outgoing_ip' => 'sometimes|boolean',
            'file_denylist' => 'array',
            'features' => 'sometimes|array',
            'workshop_enabled' => 'sometimes|boolean',
            'startup' => 'required|string',
            'config_from' => 'sometimes|bail|nullable|numeric',
            'config_stop' => 'required_without:config_from|nullable|string|max:191',
            'config_startup' => 'required_without:config_from|nullable|json',
            'config_logs' => 'required_without:config_from|nullable|json',
            'config_files' => 'required_without:config_from|nullable|json',
            'gamedig' => 'nullable|string|max:191',
            'warn_slot_mismatch' => 'sometimes|boolean',
            'warn_hostname_branding' => 'sometimes|boolean',
        ];

        if ($this->method() === 'POST') {
            $rules['nest_id'] = 'required|numeric|exists:nests,id';
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        $validator->sometimes('config_from', 'exists:eggs,id', function () {
            return (int) $this->input('config_from') !== 0;
        });
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();
        $features = array_values(array_unique(array_filter(array_get($data, 'features', []))));

        if ($this->has('workshop_enabled')) {
            if ($this->boolean('workshop_enabled')) {
                if (!in_array('workshop', $features, true)) {
                    $features[] = 'workshop';
                }
            } else {
                $features = array_values(array_filter(
                    $features,
                    fn (string $feature) => $feature !== 'workshop'
                ));
            }
        }

        return array_merge($data, [
            'force_outgoing_ip' => array_get($data, 'force_outgoing_ip', false),
            'warn_slot_mismatch' => array_get($data, 'warn_slot_mismatch', false),
            'warn_hostname_branding' => array_get($data, 'warn_hostname_branding', false),
            'features' => $features,
        ]);
    }
}
