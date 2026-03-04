<?php

namespace Pterodactyl\Http\Requests\Api\Client\Servers\Firewall;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreFirewallRuleRequest extends ClientApiRequest
{
    public function permission(): string
    {
        return 'firewall.create';
    }

    public function rules(): array
    {
        return [
            'ip'     => 'required|string|ip|max:45',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
