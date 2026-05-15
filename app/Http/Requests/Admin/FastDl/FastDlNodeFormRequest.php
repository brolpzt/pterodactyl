<?php

namespace Pterodactyl\Http\Requests\Admin\FastDl;

use Pterodactyl\Models\FastDlNode;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class FastDlNodeFormRequest extends AdminFormRequest
{
    /**
     * @return array
     */
    public function rules(): array
    {
        $storageType = $this->input('storage_type', FastDlNode::STORAGE_SSH);
        $isUpdate = $this->isMethod('PATCH') || $this->isMethod('PUT');

        $rules = [
            'name' => 'required|string|max:191',
            'location_id' => 'required|numeric|exists:locations,id',
            'storage_type' => 'required|in:ssh,s3',
            'fqdn' => 'required|string|max:191',
            'remote_path' => 'required|string|max:191',
            'sync_patterns' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];

        if ($storageType === FastDlNode::STORAGE_S3) {
            return array_merge($rules, [
                'bucket' => 'required|string|max:191',
                'endpoint' => 'required|string|max:512',
                'region' => 'nullable|string|max:64',
                'access_key' => ($isUpdate ? 'nullable' : 'required') . '|string',
                'secret_key' => ($isUpdate ? 'nullable' : 'required') . '|string',
                'use_path_style_endpoint' => 'sometimes|boolean',
                'public_url' => 'nullable|string|max:512',
                'port' => 'nullable|numeric|between:1,65535',
                'username' => 'nullable|string|max:191',
                'password' => 'nullable|string',
                'private_key' => 'nullable|string',
            ]);
        }

        return array_merge($rules, [
            'port' => 'required|numeric|between:1,65535',
            'username' => 'required|string|max:191',
            'password' => 'nullable|string',
            'private_key' => 'nullable|string',
            'bucket' => 'nullable|string|max:191',
            'endpoint' => 'nullable|string|max:512',
            'region' => 'nullable|string|max:64',
            'access_key' => 'nullable|string',
            'secret_key' => 'nullable|string',
            'use_path_style_endpoint' => 'sometimes|boolean',
            'public_url' => 'nullable|string|max:512',
        ]);
    }

    /**
     * @return array
     */
    public function attributes(): array
    {
        return [
            'fqdn' => 'FQDN / public host',
            'endpoint' => 'S3 endpoint',
            'public_url' => 'public URL',
        ];
    }
}
