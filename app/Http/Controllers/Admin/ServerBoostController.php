<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Boost\ServerBoostService;

class ServerBoostController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private ServerBoostService $service)
    {
    }

    public function index(Request $request): View
    {
        $token = $this->service->token();
        $eggIds = $this->service->eggIds();

        return view('admin.boost.index', array_merge($this->dashboard($request), [
            'token' => $token,
            'serversUrl' => $token ? route('api:boost.servers', $token) : null,
            'connectUrl' => $token ? route('api:boost.connect', $token) : null,
            'eggs' => Egg::query()->orderBy('name')->get(['id', 'name']),
            'eggIds' => $eggIds,
            'clientTab' => $this->service->clientTabEnabled(),
            'servers' => $this->service->eligibleQuery()->get(),
        ]));
    }

    /** Dashboard part only, for the page's auto-refresh. */
    public function live(Request $request): JsonResponse
    {
        return new JsonResponse(['html' => view('admin.boost._dashboard', $this->dashboard($request))->render()]);
    }

    public function updateEggs(Request $request): RedirectResponse
    {
        $data = $request->validate(['eggs' => 'array', 'eggs.*' => 'integer|exists:eggs,id']);
        $this->service->setEggIds($data['eggs'] ?? []);
        $this->alert->success('Eggs do Server Boost salvos.')->flash();

        return redirect()->route('admin.boost');
    }

    public function clientTab(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $this->service->setClientTabEnabled($enabled);
        $this->alert->success($enabled
            ? 'Os donos dos servidores agora veem a aba Server Boost no painel.'
            : 'A aba Server Boost do painel do cliente agora aparece só para admins.')->flash();

        return redirect()->route('admin.boost');
    }

    public function regenerateToken(): RedirectResponse
    {
        $this->service->regenerateToken();
        $this->alert->success('Novo token gerado. Atualize as URLs no sistema de boost: o token anterior parou de funcionar.')->flash();

        return redirect()->route('admin.boost');
    }

    public function toggleServer(Request $request, Server $server): RedirectResponse
    {
        $hidden = $request->boolean('hidden');
        Server::query()->whereKey($server->id)->update(['boost_hidden' => $hidden]);
        $this->alert->success($hidden
            ? "{$server->name} não aparece mais na lista do boost."
            : "{$server->name} voltou para a lista do boost.")->flash();

        return $request->input('back') === 'manage'
            ? redirect()->route('admin.servers.view.manage', $server->id)
            : redirect()->route('admin.boost', array_filter($request->only('range', 'server')));
    }

    /** @return array<string, mixed> */
    private function dashboard(Request $request): array
    {
        $range = array_key_exists((string) $request->query('range'), ServerBoostService::RANGES)
            ? (string) $request->query('range')
            : '24h';
        $serverId = ctype_digit((string) $request->query('server')) ? (int) $request->query('server') : null;
        $search = trim((string) $request->query('q', '')) ?: null;

        return [
            'range' => $range,
            'serverId' => $serverId,
            'search' => $search,
            'stats' => $this->service->stats($range, $serverId),
            'connections' => $this->service->recent($serverId, $search),
            'tz' => (string) config('app.stats_timezone', config('app.timezone', 'UTC')),
        ];
    }
}
