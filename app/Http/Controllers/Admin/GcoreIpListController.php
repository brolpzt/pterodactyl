<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreIpListLog;
use Pterodactyl\Models\GcoreIpListBinding;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListRefreshService;

class GcoreIpListController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private GcoreIpListRefreshService $refresh,
    ) {
    }

    public function index(): View
    {
        $lists = GcoreIpList::query()->orderBy('name')->get();

        return view('admin.gcore.ip-lists.index', [
            'lists' => $lists,
            'usage' => $this->usageByList(),
            'logs' => GcoreIpListLog::query()->with('list:id,name')->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $list = GcoreIpList::query()->create($data);

        $log = $this->refresh->refresh($list, 'manual');
        $this->flashLog($list, $log);

        return redirect()->route('admin.gcore.ip-lists.view', $list->id);
    }

    public function view(GcoreIpList $list): View
    {
        return view('admin.gcore.ip-lists.view', [
            'list' => $list,
            'usage' => $this->usageByList()[$list->id] ?? [],
            'logs' => $list->logs()->limit(100)->get(),
        ]);
    }

    public function update(Request $request, GcoreIpList $list): RedirectResponse
    {
        $data = $this->validated($request, $list);
        $urlChanged = $data['url'] !== $list->url;
        $list->fill($data)->save();

        if ($urlChanged) {
            // A new source is expected to differ: still refuse empty/broken lists, but not the shrink check.
            $this->flashLog($list, $this->refresh->refresh($list, 'force'));
        } else {
            $this->alert->success('Lista salva.')->flash();
        }

        return redirect()->route('admin.gcore.ip-lists.view', $list->id);
    }

    public function refresh(Request $request, GcoreIpList $list): RedirectResponse
    {
        $log = $this->refresh->refresh($list, $request->boolean('force') ? 'force' : 'manual');
        $this->flashLog($list, $log);

        return redirect()->route('admin.gcore.ip-lists.view', $list->id);
    }

    public function delete(GcoreIpList $list): RedirectResponse
    {
        $usage = $this->usageByList()[$list->id] ?? [];
        if ($usage !== []) {
            $this->alert->danger(sprintf(
                'A lista "%s" está em uso em %d regra(s) (perfis %s). Desmarque-a no editor do perfil antes de excluir.',
                $list->name,
                count($usage),
                implode(', ', array_unique(array_map(fn ($u) => $u['ip'] ?: '#' . $u['profile_id'], $usage))),
            ))->flash();

            return redirect()->route('admin.gcore.ip-lists.view', $list->id);
        }

        $list->delete();
        $this->alert->success('Lista excluída.')->flash();

        return redirect()->route('admin.gcore.ip-lists');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?GcoreIpList $list = null): array
    {
        $rules = GcoreIpList::$validationRules;
        $rules['name'] = ['required', 'string', 'max:64', Rule::unique('gcore_ip_lists', 'name')->ignore($list?->id)];
        $request->merge(['enabled' => $request->boolean('enabled')]);

        return $request->validate($rules);
    }

    /**
     * Rules using each list: list id => [{profile_id, ip, policy, status}].
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function usageByList(): array
    {
        $out = [];
        foreach (GcoreIpListBinding::query()->orderBy('profile_id')->get() as $binding) {
            foreach ($binding->listIds() as $id) {
                $out[$id][] = [
                    'profile_id' => $binding->profile_id,
                    'ip' => $binding->profile_ip,
                    'policy' => $binding->policy,
                    'proto' => implode(',', $binding->proto_list ?? []) ?: 'any',
                    'dports' => $binding->dport_list ?? [],
                    'status' => $binding->status,
                    'error' => $binding->last_error,
                    'applied_at' => $binding->applied_at,
                ];
            }
        }

        return $out;
    }

    private function flashLog(GcoreIpList $list, GcoreIpListLog $log): void
    {
        $text = $list->name . ': ' . $log->message;
        match ($log->status) {
            'changed', 'unchanged' => $this->alert->success($text)->flash(),
            'rejected' => $this->alert->warning($text)->flash(),
            default => $this->alert->danger($text)->flash(),
        };
    }
}
