<?php

namespace Pterodactyl\Http\Requests\Admin\Cloudflare;

use Illuminate\Validation\Validator;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class CloudflareZoneFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        $publicDomainRule = 'required|string|max:191';

        if ($this->route('zone')) {
            $publicDomainRule .= '|unique:cloudflare_zones,public_domain,' . $this->route('zone')->id;
        } else {
            $publicDomainRule .= '|unique:cloudflare_zones,public_domain';
        }

        return [
            'label' => 'required|string|max:191',
            'domain' => 'required|string|max:191',
            'public_domain' => $publicDomainRule,
            'is_active' => 'sometimes|boolean',
            'allow_user_create' => 'sometimes|boolean',
            'default_proxied' => 'sometimes|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $zoneDomain = strtolower(trim((string) $this->input('domain')));
            $publicDomain = strtolower(trim((string) $this->input('public_domain')));

            if ($zoneDomain === '' || $publicDomain === '') {
                return;
            }

            if ($publicDomain !== $zoneDomain && !str_ends_with($publicDomain, '.' . $zoneDomain)) {
                $validator->errors()->add(
                    'public_domain',
                    'O domínio público deve ser igual à zona Cloudflare ou um subdomínio dela.'
                );
            }
        });
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        return array_merge($data, [
            'label' => trim($data['label']),
            'domain' => strtolower(trim($data['domain'])),
            'public_domain' => strtolower(trim($data['public_domain'])),
            'is_active' => $this->boolean('is_active'),
            'allow_user_create' => $this->boolean('allow_user_create'),
            'default_proxied' => $this->boolean('default_proxied'),
        ]);
    }
}
