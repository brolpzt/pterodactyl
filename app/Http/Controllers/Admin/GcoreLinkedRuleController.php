<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\GcoreLinkedRule;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Gcore\IpLists\IpListParser;
use Pterodactyl\Services\Gcore\GcoreLinkedRuleService;

class GcoreLinkedRuleController extends Controller
{
    public function __construct(private AlertsMessageBag $alert, private GcoreLinkedRuleService $service)
    {
    }

    public function index(Request $request): View
    {
        $rules = GcoreLinkedRule::query()->orderBy('name')->get();

        return view('admin.gcore.linked-rules', [
            'rules' => $rules,
            'usage' => $this->service->usage(),
            'editing' => $request->filled('edit') ? $rules->firstWhere('id', (int) $request->query('edit')) : null,
            'policies' => GcoreClient::POLICIES,
            'protos' => array_values(array_diff(GcoreClient::PROTOCOLS, ['any'])),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        GcoreLinkedRule::query()->create($this->validated($request));
        $this->alert->success('Regra vinculada criada. Marque-a nos slots de porta dos eggs ("Também incluir em") e rode o sync do node.')->flash();

        return redirect()->route('admin.gcore.linked-rules');
    }

    public function update(Request $request, GcoreLinkedRule $rule): RedirectResponse
    {
        $rule->fill($this->validated($request, $rule))->save();
        $this->alert->success('Regra vinculada salva. Atenção: a regra que já existe na Gcore só é reconhecida se tiver o mesmo formato; rode o sync para conferir.')->flash();

        return redirect()->route('admin.gcore.linked-rules');
    }

    public function delete(GcoreLinkedRule $rule): RedirectResponse
    {
        if (isset($this->service->usage()[$rule->id])) {
            $this->alert->danger('Esta regra ainda está marcada em slots de eggs. Desmarque nos eggs antes de excluir.')->flash();

            return redirect()->route('admin.gcore.linked-rules');
        }
        $rule->delete();
        $this->alert->success('Regra vinculada excluída. A regra na Gcore não foi apagada: remova pelo editor do perfil se quiser.')->flash();

        return redirect()->route('admin.gcore.linked-rules');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?GcoreLinkedRule $rule = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('gcore_linked_rules', 'name')->ignore($rule?->id)],
            'policy' => ['required', 'string', Rule::in(GcoreClient::POLICIES)],
            'proto' => ['nullable', 'string', Rule::in(array_diff(GcoreClient::PROTOCOLS, ['any']))],
            'sip_list' => ['nullable', 'string', 'max:5000', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (self::splitSips((string) $value) as $sip) {
                    $ipv6 = str_contains($sip, ':') && filter_var(explode('/', $sip, 2)[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
                    if (!$ipv6 && IpListParser::parseIpv4($sip) === null) {
                        $fail("IP/CIDR inválido: {$sip}");
                    }
                }
            }],
            'mode' => ['required', 'string', Rule::in(GcoreLinkedRule::MODES)],
        ]);

        $sips = self::splitSips((string) ($data['sip_list'] ?? ''));

        return [
            'name' => trim($data['name']),
            'policy' => $data['policy'],
            'proto' => $data['proto'] ?: null,
            'sip_list' => $sips,
            'mode' => $data['mode'],
        ];
    }

    /** @return list<string> */
    private static function splitSips(string $raw): array
    {
        return array_values(array_unique(array_filter(preg_split('/[\s,;]+/', $raw) ?: [])));
    }
}
