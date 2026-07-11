<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\CloudflareDnsRecord;

class CloudflareDnsRecordTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return CloudflareDnsRecord::RESOURCE_NAME;
    }

    public function transform(CloudflareDnsRecord $record): array
    {
        return [
            'id' => $record->id,
            'zone_id' => $record->zone_id,
            'domain' => $record->zone?->domain,
            'type' => $record->type,
            'subdomain' => $record->subdomain,
            'name' => $record->name,
            'content' => $record->content,
            'ttl' => $record->ttl,
            'proxied' => $record->proxied,
            'created_at' => $record->created_at->toIso8601String(),
            'updated_at' => $record->updated_at->toIso8601String(),
        ];
    }
}
