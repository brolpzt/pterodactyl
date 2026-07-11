<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
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

    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $serverId = $request->filled('server_id') ? (int) $request->input('server_id') : null;
        $eggId = $request->filled('egg_id') ? (int) $request->input('egg_id') : null;
        $userId = $request->filled('user_id') ? (int) $request->input('user_id') : null;

        $query = ServerWorkshopItem::query()->with(['server.user', 'server.egg']);

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($builder) use ($like, $search) {
                $builder->where('title', 'like', $like)
                    ->orWhere('published_file_id', 'like', $like);

                if (ctype_digit($search)) {
                    $builder->orWhere('published_file_id', (int) $search);
                }

                $builder->orWhereHas('server', fn ($serverQuery) => $serverQuery->where('name', 'like', $like))
                    ->orWhereHas('server.user', fn ($userQuery) => $userQuery->where('username', 'like', $like));
            });
        }

        if ($serverId) {
            $query->where('server_id', $serverId);
        }

        if ($eggId) {
            $query->whereHas('server', fn ($serverQuery) => $serverQuery->where('egg_id', $eggId));
        }

        if ($userId) {
            $query->whereHas('server', fn ($serverQuery) => $serverQuery->where('owner_id', $userId));
        }

        $items = $query
            ->orderByDesc('created_at')
            ->paginate(50)
            ->appends($request->query());

        $activeServerIds = ServerWorkshopItem::query()->distinct()->pluck('server_id');

        return view('admin.workshop.index', [
            'workshopUser' => config('pterodactyl.steam.workshop_user'),
            'workshopPassConfigured' => !empty(config('pterodactyl.steam.workshop_pass')),
            'workshopAuthConfigured' => !empty(config('pterodactyl.steam.workshop_auth')),
            'steamApiKeyConfigured' => !empty(config('pterodactyl.steam.api_key')),
            'items' => $items,
            'filters' => [
                'q' => $search,
                'server_id' => $serverId,
                'egg_id' => $eggId,
                'user_id' => $userId,
            ],
            'filterServers' => Server::query()
                ->whereIn('id', $activeServerIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filterEggs' => Egg::query()
                ->whereIn('id', Server::query()->whereIn('id', $activeServerIds)->distinct()->pluck('egg_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'filterUsers' => User::query()
                ->whereIn('id', Server::query()->whereIn('id', $activeServerIds)->distinct()->pluck('owner_id'))
                ->orderBy('username')
                ->get(['id', 'username']),
            'totalItems' => ServerWorkshopItem::query()->count(),
            'totalServers' => (int) ServerWorkshopItem::query()->distinct()->count('server_id'),
            'filteredCount' => $items->total(),
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
