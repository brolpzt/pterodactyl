<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreSyncRun;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\GcoreIpListLog;
use Pterodactyl\Models\GcoreIpListBinding;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Gcore\GcoreSyncQueue;

class GcoreIpListController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private GcoreSyncQueue $queue,
    ) {
    }

    public function index(): View
    {
        $lists = GcoreIpList::query()->orderBy('name')->get();

        return view('admin.gcore.ip-lists.index', [
            'lists' => $lists,
            'usage' => $this->usageByList(),
            'logs' => GcoreIpListLog::query()->with('list:id,name')->orderByDesc('id')->limit(50)->get(),
            'runs' => GcoreDdosController::recentRuns(['kind' => GcoreSyncRun::KIND_IP_LIST], 10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $list = GcoreIpList::query()->create($data);
        $this->queued($this->queue->ipList($list, 'manual', 'lista criada por ' . $this->who($request)));

        return redirect()->route('admin.gcore.ip-lists.view', $list->id);
    }

    public function view(GcoreIpList $list): View
    {
        return view('admin.gcore.ip-lists.view', [
            'list' => $list,
            'usage' => $this->usageByList()[$list->id] ?? [],
            'logs' => $list->logs()->limit(100)->get(),
            'runs' => GcoreDdosController::recentRuns(['ip_list_id' => $list->id], 5),
        ]);
    }

    public function update(Request $request, GcoreIpList $list): RedirectResponse
    {
        $data = $this->validated($request, $list);
        $urlChanged = $data['url'] !== $list->url;
        $list->fill($data)->save();

        if ($urlChanged) {
            // A new source is expected to differ: still refuse empty/broken lists, but not the shrink check.
            $this->queued($this->queue->ipList($list, 'force', 'URL alterada por ' . $this->who($request)));
        } else {
            $this->alert->success('Lista salva.')->flash();
        }

        return redirect()->route('admin.gcore.ip-lists.view', $list->id);
    }

    public function refresh(Request $request, GcoreIpList $list): RedirectResponse
    {
        $this->queued($this->queue->ipList($list, $request->boolean('force') ? 'force' : 'manual', 'atualização manual por ' . $this->who($request)));

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
                    'verified_at' => $binding->verified_at,
                    'verify_missing' => $binding->verify_missing,
                    'verify_message' => $binding->verify_message,
                ];
            }
        }

        return $out;
    }

    private function queued(GcoreSyncRun $run): void
    {
        $run = $run->fresh() ?? $run; // with a sync queue the job already ran
        $this->alert->info(match ($run->status) {
            'ok', 'warning', 'error' => "{$run->label}: {$run->summary}",
            default => "{$run->label}: download e envio à Gcore na fila (#{$run->id}). O resultado aparece no log abaixo em alguns segundos.",
        })->flash();
    }

    private function who(Request $request): string
    {
        return (string) ($request->user()?->username ?? '?');
    }
}
