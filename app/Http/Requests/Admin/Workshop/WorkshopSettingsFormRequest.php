<?php

namespace Pterodactyl\Http\Requests\Admin\Workshop;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class WorkshopSettingsFormRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'pterodactyl:steam:workshop_user' => 'nullable|string|max:191',
            'pterodactyl:steam:workshop_pass' => 'nullable|string|max:191',
            'pterodactyl:steam:workshop_auth' => 'nullable|string|max:191',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function normalized(): array
    {
        return [
            'pterodactyl:steam:workshop_user' => $this->filled('pterodactyl:steam:workshop_user')
                ? trim((string) $this->input('pterodactyl:steam:workshop_user'))
                : '',
            'pterodactyl:steam:workshop_pass' => $this->has('pterodactyl:steam:workshop_pass')
                ? (string) $this->input('pterodactyl:steam:workshop_pass')
                : null,
            'pterodactyl:steam:workshop_auth' => $this->has('pterodactyl:steam:workshop_auth')
                ? (string) $this->input('pterodactyl:steam:workshop_auth')
                : null,
        ];
    }
}
