<?php

namespace Pterodactyl\Http\Requests\Admin\Cloudflare;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class CloudflareZoneFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $domainRule = 'required|string|max:191';

        if ($this->route('zone')) {
            $domainRule .= '|unique:cloudflare_zones,domain,' . $this->route('zone')->id;
        } else {
            $domainRule .= '|unique:cloudflare_zones,domain';
        }

        return [
            'domain' => $domainRule,
            'is_active' => 'sometimes|boolean',
            'allow_user_create' => 'sometimes|boolean',
            'default_proxied' => 'sometimes|boolean',
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return array_merge($data, [
            'is_active' => $this->boolean('is_active'),
            'allow_user_create' => $this->boolean('allow_user_create'),
            'default_proxied' => $this->boolean('default_proxied'),
        ]);
    }
}
