<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Firewall;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class DeleteFirewallRuleRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'firewall.delete';
    }

    public function rules(): array
    {
        return [];
    }
}
