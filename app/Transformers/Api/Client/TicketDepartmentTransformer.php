<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\TicketDepartment;

class TicketDepartmentTransformer extends BaseClientTransformer
{
    /**
     * @return string
     */
    public function getResourceName(): string
    {
        return 'ticket_department';
    }

    /**
     * @param \Pterodactyl\Models\TicketDepartment $department
     * @return array
     */
    public function transform(TicketDepartment $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'description' => $department->description,
        ];
    }
}
