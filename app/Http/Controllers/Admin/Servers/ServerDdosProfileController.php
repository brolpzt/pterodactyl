<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Gcore\DdosProfileService;

/**
 * Server tab: the game port's Anti-DDoS profile, set by the admin without cooldown.
 */
class ServerDdosProfileController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private DdosProfileService $service)
    {
    }

    public function index(Server $server): View
    {
        $server->loadMissing(['egg', 'node']);
        $context = $this->service->context($server);

        return view('admin.servers.view.ddos', [
            'server' => $server,
            'context' => $context,
            'nextClientChange' => $this->service->nextClientChangeAt($context),
            'sync' => $this->service->syncStatus($context['state']),
            'policies' => GcoreClient::POLICIES,
        ]);
    }

    /**
     * choice = "default" | "profile:<id>" | "policy:<gcore policy>".
     */
    public function update(Request $request, Server $server): RedirectResponse
    {
        $choice = (string) $request->validate(['choice' => 'required|string|max:80'])['choice'];

        try {
            if ($choice === 'default') {
                $this->service->changeByAdmin($server, null, null, $request->user());
            } elseif (str_starts_with($choice, 'profile:')) {
                $this->service->changeByAdmin($server, (int) substr($choice, 8), null, $request->user());
            } elseif (str_starts_with($choice, 'policy:')) {
                $this->service->changeByAdmin($server, null, substr($choice, 7), $request->user());
            } else {
                throw new DisplayException('Escolha inválida.');
            }
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.servers.view.ddos', $server->id);
        }

        $this->alert->success('Perfil anti-DDoS alterado. A sincronização com a Gcore foi colocada na fila.')->flash();

        return redirect()->route('admin.servers.view.ddos', $server->id);
    }

    public function settings(Request $request, Server $server): RedirectResponse
    {
        $this->service->setClientLocked($server, !$request->boolean('client_visible'));
        if ($request->boolean('reset_cooldown')) {
            $this->service->resetCooldown($server);
        }

        $this->alert->success('Configurações de mudança do cliente salvas.')->flash();

        return redirect()->route('admin.servers.view.ddos', $server->id);
    }
}
