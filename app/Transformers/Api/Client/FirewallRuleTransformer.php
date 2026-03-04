<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\FirewallRule;

class FirewallRuleTransformer extends BaseClientTransformer
{
    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return FirewallRule::RESOURCE_NAME;
    }

    /**
     * Transform the firewall rule model into a representative array for the client API.
     */
    public function transform(FirewallRule $rule): array
    {
        return [
            'id'         => $rule->id,
            'ip'         => $rule->ip,
            'reason'     => $rule->reason,
            'created_at' => $rule->created_at->toIso8601String(),
            'updated_at' => $rule->updated_at->toIso8601String(),
        ];
    }
}
