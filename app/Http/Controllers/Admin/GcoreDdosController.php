<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreSyncRun;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\GcoreIpListBinding;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Gcore\GcoreAclHelper;
use Pterodactyl\Services\Gcore\GcoreProfileCache;
use Pterodactyl\Services\Eggs\PortSlotSyncService;
use Pterodactyl\Services\Gcore\GcoreLinkedRuleService;
use Pterodactyl\Services\Gcore\GcoreAclRuleNoteService;
use Pterodactyl\Services\Gcore\IpLists\GcoreIpListBindingService;

class GcoreDdosController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    /**
     * Renders the last known profile list at once; the page then calls liveProfiles() to refresh it.
     */
    public function index(GcoreProfileCache $cache): View
    {
        $configured = (string) config('gcore.api_key', '') !== '';
        $cached = $configured ? $cache->list() : null;

        return view('admin.gcore.index', [
            'runs' => self::recentRuns([], 20),
            'profiles' => $cached['data'] ?? null,
            'fetchedAt' => $cached['fetched_at'] ?? null,
            'configured' => $configured,
        ]);
    }

    /**
     * Sync runs list for the pages' auto-refresh (filters: ip, ip_list_id).
     */
    public function liveRuns(Request $request): JsonResponse
    {
        $filter = array_filter([
            'ip' => $request->query('ip'),
            'ip_list_id' => $request->query('ip_list_id'),
            'kind' => $request->query('kind'),
        ], fn ($v) => $v !== null && $v !== '');
        $runs = self::recentRuns($filter, $filter === [] ? 20 : 5);

        return new JsonResponse([
            'html' => view('admin.gcore._runs', ['runs' => $runs])->render(),
            'pending' => $runs->contains(fn (GcoreSyncRun $r) => $r->isPending()),
        ]);
    }

    /**
     * @param array{ip?: string, ip_list_id?: int|string, kind?: string} $filter
     *
     * @return \Illuminate\Support\Collection<int, GcoreSyncRun>
     */
    public static function recentRuns(array $filter, int $limit): \Illuminate\Support\Collection
    {
        $query = GcoreSyncRun::query()->orderByDesc('id')->limit($limit);
        if (isset($filter['ip'])) {
            $query->where('kind', GcoreSyncRun::KIND_PORTS)->where('ip', (string) $filter['ip']);
        }
        if (isset($filter['kind'])) {
            $query->where('kind', (string) $filter['kind']);
        }
        if (isset($filter['ip_list_id'])) {
            $query->where('kind', GcoreSyncRun::KIND_IP_LIST)->where('ip_list_id', (int) $filter['ip_list_id']);
        }

        return $query->get();
    }

    public function liveProfiles(): JsonResponse
    {
        try {
            $profiles = GcoreClient::fromConfig()->listProfiles();
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }

        return new JsonResponse([
            'html' => view('admin.gcore._profiles', ['profiles' => $profiles])->render(),
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Fresh read of one profile for the editor's background check: the page compares the hash
     * with the one it was rendered from and reloads (or warns) when Gcore has something else.
     */
    public function liveProfile(int $profile): JsonResponse
    {
        try {
            $data = GcoreClient::fromConfig()->getProfile($profile);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 502);
        }
        $status = (string) ($data['status']['status'] ?? '-');

        return new JsonResponse([
            'hash' => GcoreProfileCache::formHash($data),
            'status' => $status,
            'pending' => str_contains(strtolower($status), 'pending'),
            'fetched_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * CIDRs of every IP list, loaded by the editor after the page (they can be thousands).
     * ETag on ids/updated_at so an unchanged set costs a 304.
     */
    public function ipListEntries(Request $request): JsonResponse
    {
        $meta = GcoreIpList::query()->orderBy('id')->get(['id', 'entry_count', 'updated_at']);
        $etag = sha1($meta->map(fn (GcoreIpList $l) => $l->id . ':' . $l->entry_count . ':' . $l->updated_at?->getTimestamp())->join('|'));

        $response = new JsonResponse();
        $response->setEtag($etag);
        $response->headers->set('Cache-Control', 'private, no-cache');
        if ($response->isNotModified($request)) {
            return $response;
        }

        $lists = [];
        foreach (GcoreIpList::query()->get(['id', 'entries']) as $list) {
            $lists[(string) $list->id] = $list->entryList();
        }

        return $response->setData(['lists' => (object) $lists]);
    }

    /**
     * Renders from the cached copy when there is one (the page revalidates via liveProfile());
     * only a first visit, or ?fresh=1, waits for the Gcore API.
     */
    public function show(Request $request, int $profile, PortSlotSyncService $portSlots, GcoreIpListBindingService $bindings, GcoreAclRuleNoteService $notes, GcoreProfileCache $cache, GcoreLinkedRuleService $linked): View|RedirectResponse
    {
        try {
            $cached = $request->boolean('fresh') ? null : $cache->get($profile);
            if ($cached === null) {
                $data = GcoreClient::fromConfig()->getProfile($profile);
                $fetchedAt = now();
            } else {
                $data = $cached['data'];
                $fetchedAt = $cached['fetched_at'];
            }
            $form = GcoreAclHelper::extractProfileFormData($data);
            $linkedNames = $linked->describe($form['acl']);
            $annotated = $bindings->annotate($profile, $form['acl']);
            $described = $notes->annotate($profile, $annotated['acl']);
            $form['acl'] = $described['acl'];

            return view('admin.gcore.profile', [
                'profile' => $data,
                'form' => $form,
                'policies' => GcoreClient::POLICIES,
                'portMap' => $this->buildPortMap((string) ($data['ip_address'] ?? ''), $portSlots),
                // Entries come later from ipListEntries(): they are the bulk of the page otherwise.
                'ipLists' => GcoreIpList::query()->orderBy('name')
                    ->get(['id', 'name', 'entry_count', 'enabled', 'last_status'])
                    ->map(fn (GcoreIpList $l) => [
                        'id' => $l->id,
                        'name' => $l->name,
                        'count' => $l->entry_count,
                        'enabled' => $l->enabled,
                        'status' => $l->last_status,
                    ])->values(),
                'formHash' => GcoreProfileCache::formHash($data),
                'linkedNames' => $linkedNames,
                'runs' => self::recentRuns(['ip' => (string) ($data['ip_address'] ?? '')], 3),
                'fetchedAt' => $fetchedAt,
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

                // The page may have been rendered from the cache or left open: never overwrite
                // a profile that changed on Gcore since then.
                $loaded = (string) $request->input('form_hash', '');
                if ($loaded !== '' && !hash_equals(GcoreProfileCache::formHash($data), $loaded)) {
                    throw new DisplayException('Nada foi enviado: este perfil mudou na Gcore depois que a página foi aberta (sync de portas, listas de IP, outra aba ou o painel da Gcore). A página abaixo já mostra a versão atual; refaça a alteração.');
                }

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
                $put = $client->updateProfileChecked($profile, $data, $payload);
                $updated = $put['response'];
                $bindings->storeBindings($profile, (string) ($data['ip_address'] ?? ''), $prepared['bindings']);
                $notes->store($profile, $formAcl);
                $listNote = $prepared['bindings'] === [] ? '' : ' · ' . count($prepared['bindings']) . ' regra(s) ligada(s) a listas de IP';

                $overrideNote = $overrides === []
                    ? ''
                    : ' · ' . $this->applyOverrides((string) ($data['ip_address'] ?? ''), $overrides);

                $text = 'Enviado à Gcore · ' . ($updated['status']['status'] ?? 'OK') . ' (propagação ~2–5 min)'
                    . ($put['recovered'] === null ? '' : ' · ' . $put['recovered'])
                    . $listNote . $overrideNote;
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
