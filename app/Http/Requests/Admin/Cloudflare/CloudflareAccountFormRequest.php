<?php

namespace Pterodactyl\Http\Requests\Admin\Cloudflare;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class CloudflareAccountFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'api_token' => 'nullable|string',
        ];
    }
}
