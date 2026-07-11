<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Models\ServerWorkshopItem;
use Pterodactyl\Providers\SettingsServiceProvider;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Admin\Workshop\WorkshopSettingsFormRequest;

class WorkshopController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private Encrypter $encrypter,
        private SettingsRepositoryInterface $settings,
    ) {
    }

    public function index(): View
    {
        $items = ServerWorkshopItem::query()
            ->with(['server.user', 'server.egg'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.workshop.index', [
            'workshopUser' => config('pterodactyl.steam.workshop_user'),
            'workshopPassConfigured' => !empty(config('pterodactyl.steam.workshop_pass')),
            'workshopAuthConfigured' => !empty(config('pterodactyl.steam.workshop_auth')),
            'steamApiKeyConfigured' => !empty(config('pterodactyl.steam.api_key')),
            'items' => $items,
            'totalItems' => ServerWorkshopItem::query()->count(),
            'totalServers' => (int) ServerWorkshopItem::query()->distinct()->count('server_id'),
        ]);
    }

    /**
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function update(WorkshopSettingsFormRequest $request): RedirectResponse
    {
        $values = $request->normalized();

        $clearPass = ($values['pterodactyl:steam:workshop_pass'] ?? null) === '!e';
        $clearAuth = ($values['pterodactyl:steam:workshop_auth'] ?? null) === '!e';

        if ($clearPass) {
            $values['pterodactyl:steam:workshop_pass'] = '';
        }

        if ($clearAuth) {
            $values['pterodactyl:steam:workshop_auth'] = '';
        }

        foreach ($values as $key => $value) {
            if (in_array($key, ['pterodactyl:steam:workshop_pass', 'pterodactyl:steam:workshop_auth'], true)
                && $value === ''
                && !(($key === 'pterodactyl:steam:workshop_pass' && $clearPass) || ($key === 'pterodactyl:steam:workshop_auth' && $clearAuth))
            ) {
                continue;
            }

            if (in_array($key, SettingsServiceProvider::getEncryptedKeys(), true) && !empty($value)) {
                $value = $this->encrypter->encrypt($value);
            }

            $this->settings->set('settings::' . $key, $value);
        }

        $this->alert->success('Configurações Steam Workshop atualizadas com sucesso.')->flash();

        return redirect()->route('admin.workshop');
    }
}
