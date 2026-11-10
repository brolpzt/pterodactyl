<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Services\Gcore\GcoreAclHelper;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreIpListBinding;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListBindingService;
use Pterodactyl\Services\Gcore\GcoreAclRuleNoteService;

class GcoreDdosController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        $error = null;
        $profiles = [];
        $configured = (string) config('gcore.api_key', '') !== '';

        if ($configured) {
            try {
                $profiles = GcoreClient::fromConfig()->listProfiles();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.gcore.index', [
            'profiles' => $profiles,
            'error' => $error,
            'configured' => $configured,
        ]);
    }

    public function show(int $profile, PortSlotSyncService $portSlots, GcoreIpListBindingService $bindings, GcoreAclRuleNoteService $notes): View|RedirectResponse
    {
        try {
            $client = GcoreClient::fromConfig();
            $data = $client->getProfile($profile);
            $form = GcoreAclHelper::extractProfileFormData($data);
            $annotated = $bindings->annotate($profile, $form['acl']);
            $described = $notes->annotate($profile, $annotated['acl']);
            $form['acl'] = $described['acl'];

            return view('admin.gcore.profile', [
                'profile' => $data,
                'form' => $form,
                'policies' => GcoreClient::POLICIES,
                'portMap' => $this->buildPortMap((string) ($data['ip_address'] ?? ''), $portSlots),
                'ipLists' => GcoreIpList::query()->orderBy('name')->get()->map(fn (GcoreIpList $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'count' => $l->entry_count,
                    'enabled' => $l->enabled,
                    'status' => $l->last_status,
                    'entries' => $l->entryList(),
                ])->values(),
                'orphanBindings' => count($annotated['orphans']),
                'listChecks' => GcoreIpListBinding::query()->where('profile_id', $profile)->where('status', '!=', 'orphan')->get(),
                'lostNotes' => $described['lost'],
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->alert->danger($e->getMessage())->flash();

            return redirect()->route('admin.gcore');
        }
    }

    public function update(Request $request, int $profile, GcoreIpListBindingService $bindings, GcoreAclRuleNoteService $notes): RedirectResponse
    {
        $overrides = $this->parseOverrides((string) $request->input('port_overrides', ''));

        try {
            $formAcl = GcoreAclHelper::aclFromPost($request->all());
            $prepared = $bindings->prepareForSave($formAcl);
            $client = GcoreClient::fromConfig();

            GcoreIpListBindingService::withProfileLock($profile, function () use ($request, $profile, $client, $prepared, $bindings, $overrides, $notes, $formAcl) {
                $data = $client->getProfile($profile);

                $rate = [
                    'low' => max(1, min(50, (int) $request->input('rate_low', 50))),
                    'medium' => max(50, min(150, (int) $request->input('rate_medium', 150))),
                    'high' => max(150, min(300, (int) $request->input('rate_high', 300))),
                    'geo' => max(1, min(300, (int) $request->input('rate_geo', 300))),
                ];

                $geoRaw = trim((string) $request->input('geoip_list', ''));
                $geoip = $geoRaw === '' ? [] : preg_split('/[\s,;]+/', strtoupper($geoRaw));
                $geoip = array_values(array_filter($geoip ?: []));

                $payload = $client->buildUpdatePayload($data, $rate, $geoip, $prepared['acl']);
                $updated = $client->updateProfile($profile, $payload);
                $bindings->storeBindings($profile, (string) ($data['ip_address'] ?? ''), $prepared['bindings']);
                $notes->store($profile, $formAcl);
                $listNote = $prepared['bindings'] === [] ? '' : ' · ' . count($prepared['bindings']) . ' regra(s) ligada(s) a listas de IP';

                $overrideNote = $overrides === []
                    ? ''
                    : ' · ' . $this->applyOverrides((string) ($data['ip_address'] ?? ''), $overrides);

                $text = 'Enviado à Gcore · ' . ($updated['status']['status'] ?? 'OK') . ' (propagação ~2–5 min)' . $listNote . $overrideNote;
                if ($prepared['bindings'] === []) {
                    $this->alert->success($text)->flash();

                    return;
                }

                // Read the profile back and confirm every list CIDR made it into its rule.
                try {
                    $check = $bindings->verify($profile);
                } catch (\Throwable $e) {
                    $this->alert->warning($text . ' · não foi possível conferir na Gcore: ' . $e->getMessage())->flash();

                    return;
                }
                if ($check['missing'] > 0) {
                    $this->alert->danger($text . ' · ' . $check['message'] . '. O painel vai reenviar automaticamente a cada 15 min.')->flash();
                } else {
                    $this->alert->success($text . ' · ' . $check['message'])->flash();
                }
            });
        } catch (\Throwable $e) {
            $this->alert->danger($e->getMessage())->flash();
        }

        return redirect()->route('admin.gcore.profile', $profile);
    }

    /**
     * Panel allocations on the profile IP, keyed by port, so the ACL editor can show
     * which server/slot owns each dport and which policy the egg expects for it.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function buildPortMap(string $ip, PortSlotSyncService $portSlots): array
    {
        if ($ip === '') {
            return [];
        }

        $allocations = Allocation::query()
            ->where('ip', $ip)
            ->with(['node:id,name', 'server:id,uuidShort,name,egg_id,allocation_id', 'server.egg:id,nest_id,name,port_slots,gcore_policy,gcore_proto', 'server.egg.nest:id,name'])
            ->orderBy('port')
            ->get();

        $map = [];
        foreach ($allocations as $allocation) {
            $server = $allocation->server;
            $entry = [
                'node' => $allocation->node?->name,
                'protected' => (bool) $allocation->gcore_protected,
                'server' => null,
            ];

            if ($server && $server->egg) {
                $primaryId = (int) $server->allocation_id;
                $slot = $portSlots->describeSlotForAllocation($server->egg, $allocation, $primaryId);
                $expected = $portSlots->resolveGcoreForAllocation($server->egg, $allocation, $primaryId);
                $fromEgg = $portSlots->resolveEggGcoreForAllocation($server->egg, $allocation, $primaryId);
                $override = $allocation->activeGcoreOverride();
                $entry['server'] = [
                    'id' => $server->id,
                    'name' => $server->name,
                    'url' => route('admin.servers.view', $server->id),
                    'egg' => $server->egg->name,
                    'nest' => $server->egg->nest?->name,
                    'slot' => $slot['env'] ?? null,
                    'slot_name' => $slot['name'] ?? null,
                    'primary' => (int) $allocation->id === $primaryId,
                    'policy' => $expected['policy'] ?? null,
                    'proto' => $expected['proto'] ?? null,
                    'egg_policy' => $fromEgg['policy'] ?? null,
                    'egg_proto' => $fromEgg['proto'] ?? null,
                    'override' => $override !== null,
                ];
            }

            $map[(int) $allocation->port][] = $entry;
        }

        return $map;
    }

    /**
     * Pending per-port overrides from the editor: [{port, policy, proto}] to pin,
     * [{port, clear: true}] to drop.
     *
     * @return array<int, array{policy: string, proto: string|null}|null> port => override (null = clear)
     */
    private function parseOverrides(string $raw): array
    {
        $decoded = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $port = (int) ($item['port'] ?? 0);
            if ($port < 1 || $port > 65535) {
                continue;
            }
            if (!empty($item['clear'])) {
                $out[$port] = null;
                continue;
            }
            $policy = trim((string) ($item['policy'] ?? ''));
            $proto = strtolower(trim((string) ($item['proto'] ?? '')));
            if (!in_array($policy, GcoreClient::POLICIES, true)) {
                continue;
            }
            if ($proto !== '' && $proto !== 'any' && !in_array($proto, ['tcp', 'udp'], true)) {
                continue;
            }
            $out[$port] = ['policy' => $policy, 'proto' => $proto === '' || $proto === 'any' ? null : $proto];
        }

        return $out;
    }

    /**
     * Persist overrides on the allocations of this IP that currently have a server.
     *
     * @param array<int, array{policy: string, proto: string|null}|null> $overrides
     */
    private function applyOverrides(string $ip, array $overrides): string
    {
        if ($ip === '') {
            return 'exceções ignoradas (perfil sem IP)';
        }

        $pinned = [];
        $cleared = [];
        $skipped = [];
        foreach ($overrides as $port => $override) {
            $allocations = Allocation::query()->where('ip', $ip)->where('port', $port)->get();
            if ($override === null) {
                foreach ($allocations as $allocation) {
                    $allocation->forceFill([
                        'gcore_override_policy' => null,
                        'gcore_override_proto' => null,
                        'gcore_override_server_id' => null,
                    ])->save();
                }
                $cleared[] = $port;
                continue;
            }

            $owned = $allocations->filter(fn (Allocation $a) => (bool) $a->server_id);
            if ($owned->isEmpty()) {
                $skipped[] = $port;
                continue;
            }
            foreach ($owned as $allocation) {
                $allocation->forceFill([
                    'gcore_override_policy' => $override['policy'],
                    'gcore_override_proto' => $override['proto'],
                    'gcore_override_server_id' => $allocation->server_id,
                ])->save();
            }
            $pinned[] = $port . '→' . $override['policy'] . '/' . ($override['proto'] ?? 'any');
        }

        $parts = [];
        if ($pinned) {
            $parts[] = 'fixadas: ' . implode(', ', $pinned);
        }
        if ($cleared) {
            $parts[] = 'exceção removida: ' . implode(', ', $cleared);
        }
        if ($skipped) {
            $parts[] = 'sem servidor (ignoradas): ' . implode(', ', $skipped);
        }

        return implode(' · ', $parts);
    }
}
