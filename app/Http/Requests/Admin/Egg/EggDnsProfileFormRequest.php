<?php

namespace Pterodactyl\Http\Requests\Admin\Egg;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class EggDnsProfileFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => 'sometimes|boolean',
            'allowed_types' => 'sometimes|array',
            'allowed_types.*' => 'string|in:A,CNAME,SRV',
            'default_type' => 'required|string|in:A,CNAME,SRV',
            'max_records_per_server' => 'required|integer|min:1|max:50',
            'srv_service' => 'nullable|string|max:32',
            'srv_protocol' => 'nullable|string|max:16',
            'srv_priority' => 'required|integer|min:0|max:65535',
            'srv_weight' => 'required|integer|min:0|max:65535',
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return array_merge($data, [
            'enabled' => $this->boolean('enabled'),
            'allowed_types' => $data['allowed_types'] ?? ['A'],
        ]);
    }
}
