<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\Location;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\PanelAlert;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;

/**
 * Admin CRUD for the banners shown across the panel.
 */
class PanelAlertsController extends Controller
{
    private const ID_FILTERS = [
        'nest_ids' => 'nests',
        'egg_ids' => 'eggs',
        'node_ids' => 'nodes',
        'location_ids' => 'locations',
        'server_ids' => 'servers',
    ];

    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.alerts.index', [
            'alerts' => PanelAlert::query()->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new PanelAlert([
            'placements' => [
                PanelAlert::PLACEMENT_DASHBOARD,
                PanelAlert::PLACEMENT_ACCOUNT,
                PanelAlert::PLACEMENT_SERVER,
            ],
        ]));
    }

    public function view(PanelAlert $panelAlert): View
    {
        return $this->form($panelAlert);
    }

    public function store(Request $request): RedirectResponse
    {
        $alert = PanelAlert::query()->create($this->validated($request));

        $this->alert->success('Alerta criado.')->flash();

        return redirect()->route('admin.alerts.view', $alert->id);
    }

    public function update(Request $request, PanelAlert $panelAlert): RedirectResponse
    {
        $panelAlert->update($this->validated($request));

        $this->alert->success('Alerta salvo.')->flash();

        return redirect()->route('admin.alerts.view', $panelAlert->id);
    }

    public function delete(PanelAlert $panelAlert): RedirectResponse
    {
        $panelAlert->delete();

        $this->alert->success('Alerta removido.')->flash();

        return redirect()->route('admin.alerts');
    }

    /**
     * Server search for the targeting field, as the panel can hold too many servers
     * to print them all into a select.
     */
    public function servers(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        $servers = Server::query()
            ->select(['id', 'name'])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")->orWhere('uuidShort', 'like', "{$term}%");
                    if (ctype_digit($term)) {
                        $query->orWhere('id', (int) $term);
                    }
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get();

        return new JsonResponse($servers->map(fn (Server $server) => [
            'id' => $server->id,
            'text' => "{$server->name} (#{$server->id})",
        ])->all());
    }

    private function form(PanelAlert $alert): View
    {
        return view('admin.alerts.view', [
            'alert' => $alert,
            'nests' => Nest::query()->with(['eggs' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
            'nodes' => Node::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('short')->get(),
            'selectedServers' => Server::query()
                ->whereIn('id', array_map('intval', old('server_ids', $alert->server_ids ?? [])))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    private function validated(Request $request): array
    {
        $rules = [
            'title' => 'nullable|string|max:191',
            'message' => 'required|string|max:2000',
            'type' => ['required', Rule::in(array_keys(PanelAlert::TYPES))],
            'enabled' => 'sometimes|boolean',
            'dismissible' => 'sometimes|boolean',
            'audience' => ['required', Rule::in(array_keys(PanelAlert::AUDIENCES))],
            'placements' => 'required|array|min:1',
            'placements.*' => Rule::in(array_keys(PanelAlert::PLACEMENTS)),
            'server_pages' => 'nullable|array',
            'server_pages.*' => Rule::in(array_keys(PanelAlert::SERVER_PAGES)),
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ];

        foreach (self::ID_FILTERS as $field => $table) {
            $rules[$field] = 'nullable|array';
            $rules["{$field}.*"] = "integer|exists:{$table},id";
        }

        $data = $request->validate($rules);

        foreach (array_keys(self::ID_FILTERS) as $field) {
            $data[$field] = array_values(array_unique(array_map('intval', $data[$field] ?? [])));
        }

        $data['placements'] = array_values($data['placements']);
        $data['server_pages'] = in_array(PanelAlert::PLACEMENT_SERVER, $data['placements'], true)
            ? array_values($data['server_pages'] ?? [])
            : [];
        $data['enabled'] = $request->boolean('enabled');
        $data['dismissible'] = $request->boolean('dismissible');
        $data['starts_at'] = isset($data['starts_at']) ? CarbonImmutable::parse($data['starts_at']) : null;
        $data['ends_at'] = isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null;

        return $data;
    }
}
