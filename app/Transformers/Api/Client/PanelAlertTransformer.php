<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\PanelAlert;

class PanelAlertTransformer extends BaseClientTransformer
{
    public function getResourceName(): string
    {
        return PanelAlert::RESOURCE_NAME;
    }

    public function transform(PanelAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'title' => $alert->title,
            'message_html' => $alert->renderedMessage(),
            'type' => $alert->type,
            'dismissible' => $alert->dismissible,
            'placements' => $alert->placements,
            'server_pages' => $alert->server_pages ?? [],
            'revision' => $alert->revision(),
        ];
    }
}
