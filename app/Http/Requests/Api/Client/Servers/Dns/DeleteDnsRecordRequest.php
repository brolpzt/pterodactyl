<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Dns;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class DeleteDnsRecordRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'dns.delete';
    }

    public function rules(): array
    {
        return [];
    }
}
