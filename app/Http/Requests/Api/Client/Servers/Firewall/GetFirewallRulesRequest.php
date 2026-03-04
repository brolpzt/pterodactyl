<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Firewall;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetFirewallRulesRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'firewall.read';
    }

    public function rules(): array
    {
        return [];
    }
}
