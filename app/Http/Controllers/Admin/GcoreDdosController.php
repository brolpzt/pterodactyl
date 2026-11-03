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

    public function show(int $profile, PortSlotSyncService $portSlots): View|RedirectResponse
    {
        try {
            $client = GcoreClient::fromConfig();
            $data = $client->getProfile($profile);
            $form = GcoreAclHelper::extractProfileFormData($data);

            return view('admin.gcore.profile', [
                'profile' => $data,
                'form' => $form,
                'policies' => GcoreClient::POLICIES,
                'portMap' => $this->buildPortMap((string) ($data['ip_address'] ?? ''), $portSlots),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->alert->danger($e->getMessage())->flash();

            return redirect()->route('admin.gcore');
        }
    }

    public function update(Request $request, int $profile): RedirectResponse
    {
        try {
            $client = GcoreClient::fromConfig();
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

            $acl = GcoreAclHelper::aclFromPost($request->all());
            $payload = $client->buildUpdatePayload($data, $rate, $geoip, $acl);
            $updated = $client->updateProfile($profile, $payload);

            $this->alert->success(
                'Enviado à Gcore · ' . ($updated['status']['status'] ?? 'OK') . ' (propagação ~2–5 min)'
            )->flash();
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
                ];
            }

            $map[(int) $allocation->port][] = $entry;
        }

        return $map;
    }
}
