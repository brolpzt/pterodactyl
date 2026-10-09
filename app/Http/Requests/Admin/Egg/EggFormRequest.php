<?php

namespace Pterodactyl\Http\Requests\Admin\Egg;

use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
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
            'gcore_policy' => 'nullable|string|max:64',
            'gcore_proto' => 'nullable|string|in:' . implode(',', GcoreClient::PROTOCOLS),
            'port_slots' => 'nullable|array',
            'port_slots.*.env_variable' => 'nullable|string|max:191',
            'port_slots.*.name' => 'nullable|string|max:191',
            'port_slots.*.description' => 'nullable|string|max:1000',
            'port_slots.*.required' => 'sometimes|boolean',
            'warn_slot_mismatch' => 'sometimes|boolean',
            'command_transmission_type' => 'required|string|in:stdin,rcon',
            'rcon_protocol' => 'required_if:command_transmission_type,rcon|nullable|string|in:source,quake3,webrcon',
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

        $validator->after(function ($validator) {
            try {
                app(PortSlotSyncService::class)->normalizeSlots($this->input('port_slots', []));
            } catch (DisplayException $e) {
                $validator->errors()->add('port_slots', $e->getMessage());
            }
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

        $gcorePolicy = trim((string) array_get($data, 'gcore_policy', ''));
        $gcoreProto = strtolower(trim((string) array_get($data, 'gcore_proto', '')));

        $portSlots = app(PortSlotSyncService::class)->normalizeSlots(
            array_get($data, 'port_slots', $this->input('port_slots', []))
        );

        return array_merge($data, [
            'force_outgoing_ip' => array_get($data, 'force_outgoing_ip', false),
            'warn_slot_mismatch' => array_get($data, 'warn_slot_mismatch', false),
            'features' => $features,
            'gcore_policy' => $gcorePolicy === '' ? null : $gcorePolicy,
            'gcore_proto' => $gcoreProto === '' ? null : $gcoreProto,
            'port_slots' => $portSlots === [] ? null : $portSlots,
        ]);
    }
}
