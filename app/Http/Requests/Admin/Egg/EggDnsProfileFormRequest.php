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
            'allowed_types.*' => 'string|in:A,CNAME',
            'default_type' => 'required|string|in:A,CNAME',
            'max_records_per_server' => 'required|integer|min:1|max:50',
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
