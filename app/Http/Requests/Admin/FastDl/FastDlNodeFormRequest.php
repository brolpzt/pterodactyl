<?php

namespace Pterodactyl\Http\Requests\Admin\FastDl;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class FastDlNodeFormRequest extends AdminFormRequest
{
    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'location_id' => 'required|numeric|exists:locations,id',
            'fqdn' => 'required|string|max:191',
            'port' => 'required|numeric|between:1,65535',
            'remote_path' => 'required|string|max:191',
            'username' => 'required|string|max:191',
            'password' => 'nullable|string',
            'private_key' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
