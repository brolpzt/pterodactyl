<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Dns;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetDnsRecordsRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'dns.read';
    }

    public function rules(): array
    {
        return [];
    }
}
