<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Dns;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreDnsRecordRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'dns.create';
    }

    public function rules(): array
    {
        return [
            'zone_id' => 'required|integer|exists:cloudflare_zones,id',
            'subdomain' => 'required|string|max:63',
            'type' => 'required|string|in:A,CNAME,SRV',
            'content' => 'nullable|string|max:512',
            'proxied' => 'sometimes|boolean',
        ];
    }
}
