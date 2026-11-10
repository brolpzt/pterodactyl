<?php

namespace Pterodactyl\Services\Gcore\IpLists;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Models\GcoreIpListBinding;
use Pterodactyl\Services\Gcore\GcoreAclHelper;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Keeps the sip_list of selected Gcore ACL rules in sync with IP lists.
 *
 * Gcore rules carry no id, so each binding remembers the rule's policy/proto/dport/sport
 * and the list CIDRs it last wrote (applied_sips). On refresh the rule is found again,
 * the old list CIDRs are swapped for the new ones and manual IPs are kept.
 * Rules with a sip_list are frozen for GcoreFirewallSyncService, so it never touches them.
 */
class GcoreIpListBindingService
{
    private ?GcoreClient $client = null;

    public function __construct(?GcoreClient $client = null)
    {
        $this->client = $client;
    }

    /**
     * Serialises every read-modify-write of one Gcore profile (editor save, port sync, list refresh).
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function withProfileLock(int $profileId, callable $callback): mixed
    {
        return Cache::lock('gcore:profile-acl:' . $profileId, 120)->block(60, $callback);
    }

    /** @param array<string, mixed> $rule */
    public static function signature(array $rule): string
    {
        $protos = array_map(fn ($p) => strtolower(trim((string) $p)), (array) ($rule['proto_list'] ?? []));
        sort($protos);
        $dports = array_map('intval', (array) ($rule['dport_list'] ?? []));
        sort($dports);
        $sports = array_map('intval', (array) ($rule['sport_list'] ?? []));
        sort($sports);

        return implode('|', [
            trim((string) ($rule['policy'] ?? '')),
            implode(',', $protos),
            implode(',', $dports),
            implode(',', $sports),
        ]);
    }

    /**
     * CIDRs a set of lists contributes to a rule (union, aggregated).
     *
     * @param list<int> $listIds
     * @param Collection<int, GcoreIpList>|null $lists
     * @return list<string>
     */
    public function desiredSips(array $listIds, ?Collection $lists = null): array
    {
        $lists ??= GcoreIpList::query()->whereIn('id', $listIds)->get()->keyBy('id');
        $all = [];
        foreach ($listIds as $id) {
            $list = $lists->get($id);
            if ($list) {
                array_push($all, ...$list->entryList());
            }
        }

        return IpListParser::aggregate($all);
    }

    /**
     * For the editor: mark rules bound to lists and strip the list CIDRs from their sip_list,
     * so the textbox only shows manual IPs.
     *
     * @param list<array<string, mixed>> $acl
     * @return array{acl: list<array<string, mixed>>, orphans: list<GcoreIpListBinding>}
     */
    public function annotate(int $profileId, array $acl): array
    {
        $bindings = GcoreIpListBinding::query()->where('profile_id', $profileId)->get();
        [$matches, $orphans] = $this->match($acl, $bindings);

        foreach ($matches as $idx => $binding) {
            $acl[$idx]['ip_lists'] = $binding->listIds();
            $acl[$idx]['sip_list'] = $this->manualFrom($acl[$idx], $binding);
        }

        return ['acl' => $acl, 'orphans' => $orphans];
    }

    /**
     * Editor save: expand each rule's lists into its sip_list. Returns the ACL to send and the
     * bindings to store once Gcore accepted it.
     *
     * @param list<array<string, mixed>> $acl rules from the form, each may carry 'ip_lists' => list<int>
     * @return array{acl: list<array<string, mixed>>, bindings: list<array<string, mixed>>}
     *
     * @throws DisplayException
     */
    public function prepareForSave(array $acl): array
    {
        $ids = [];
        foreach ($acl as $rule) {
            array_push($ids, ...array_map('intval', (array) ($rule['ip_lists'] ?? [])));
        }
        $lists = GcoreIpList::query()->whereIn('id', array_unique($ids))->get()->keyBy('id');

        $out = [];
        $bindings = [];
        foreach ($acl as $n => $rule) {
            $listIds = array_values(array_unique(array_filter(
                array_map('intval', (array) ($rule['ip_lists'] ?? [])),
                fn (int $id) => $lists->has($id),
            )));
            unset($rule['ip_lists'], $rule['description']);

            if ($listIds === []) {
                $out[] = $rule;
                continue;
            }

            $fromLists = $this->desiredSips($listIds, $lists);
            if ($fromLists === []) {
                // An empty sip_list on Gcore means "any source" — never let a list-only rule get there.
                throw new DisplayException(sprintf(
                    'Regra %d (%s): as listas selecionadas ainda não têm entradas. Atualize a lista antes de usá-la.',
                    $n + 1,
                    $rule['policy'] ?? '?',
                ));
            }

            $manual = array_values(array_diff((array) ($rule['sip_list'] ?? []), $fromLists));
            $rule['sip_list'] = array_values(array_merge($manual, $fromLists));
            $out[] = $rule;

            $bindings[] = [
                'policy' => (string) $rule['policy'],
                'proto_list' => array_values((array) ($rule['proto_list'] ?? [])),
                'dport_list' => array_values((array) ($rule['dport_list'] ?? [])),
                'sport_list' => array_values((array) ($rule['sport_list'] ?? [])),
                'list_ids' => $listIds,
                'manual_sips' => $manual,
                'applied_sips' => $fromLists,
            ];
        }

        return ['acl' => $out, 'bindings' => $bindings];
    }

    /**
     * Replace the profile's bindings with the ones from the last accepted editor save.
     *
     * @param list<array<string, mixed>> $bindings
     */
    public function storeBindings(int $profileId, string $profileIp, array $bindings): void
    {
        GcoreIpListBinding::query()->where('profile_id', $profileId)->delete();
        foreach ($bindings as $binding) {
            GcoreIpListBinding::query()->create($binding + [
                'profile_id' => $profileId,
                'profile_ip' => $profileIp,
                'status' => 'ok',
                'applied_at' => now(),
            ]);
        }
    }

    /**
     * Push current list contents to every profile with a binding that is out of date
     * (or that failed last time). Orphans are skipped until the profile is saved again.
     *
     * @param list<int>|null $onlyListIds limit to bindings that use one of these lists
     * @return list<array{profile_id:int, ip:string|null, status:string, rules:int, message:string}>
     */
    public function applyPending(?array $onlyListIds = null): array
    {
        $lists = GcoreIpList::query()->get()->keyBy('id');
        $byProfile = GcoreIpListBinding::query()
            ->where('status', '!=', 'orphan')
            ->get()
            ->filter(function (GcoreIpListBinding $b) use ($lists, $onlyListIds) {
                if ($onlyListIds !== null && array_intersect($b->listIds(), $onlyListIds) === []) {
                    return false;
                }

                return $b->status === 'error' || !$this->sameSet($this->desiredSips($b->listIds(), $lists), $b->applied_sips ?? []);
            })
            ->groupBy('profile_id');

        $results = [];
        foreach ($byProfile as $profileId => $bindings) {
            $results[] = $this->applyProfile((int) $profileId, $lists);
        }

        return $results;
    }

    /**
     * @param Collection<int, GcoreIpList> $lists
     * @return array{profile_id:int, ip:string|null, status:string, rules:int, message:string}
     */
    public function applyProfile(int $profileId, Collection $lists): array
    {
        $bindings = GcoreIpListBinding::query()->where('profile_id', $profileId)->where('status', '!=', 'orphan')->get();
        $ip = $bindings->first()?->profile_ip;

        try {
            return self::withProfileLock($profileId, function () use ($profileId, $bindings, $lists, &$ip) {
                $client = $this->client();
                $profile = $client->getProfile($profileId);
                $ip = (string) ($profile['ip_address'] ?? $ip);
                $form = GcoreAclHelper::extractProfileFormData($profile);
                $acl = $form['acl'];

                [$matches, $orphans] = $this->match($acl, $bindings);
                foreach ($orphans as $orphan) {
                    $orphan->forceFill([
                        'status' => 'orphan',
                        'last_error' => 'Regra não encontrada no perfil (alterada fora do painel?). Salve o perfil no editor para religar.',
                    ])->save();
                }

                $changed = 0;
                $updates = [];
                foreach ($matches as $idx => $binding) {
                    $desired = $this->desiredSips($binding->listIds(), $lists);
                    if ($desired === []) {
                        $binding->forceFill(['status' => 'error', 'last_error' => 'Listas sem entradas; regra mantida como estava.'])->save();
                        continue;
                    }
                    $manual = $this->manualFrom($acl[$idx], $binding);
                    $next = array_values(array_merge(array_values(array_diff($manual, $desired)), $desired));
                    if (!$this->sameSet($next, (array) ($acl[$idx]['sip_list'] ?? []))) {
                        $acl[$idx]['sip_list'] = $next;
                        ++$changed;
                    }
                    $updates[] = [$binding, $desired, $manual];
                }

                $status = 'unchanged';
                if ($changed > 0) {
                    $payload = $client->buildUpdatePayload($profile, $form['rate'], $form['geoip'], $acl);
                    $updated = $client->updateProfile($profileId, $payload);
                    $status = 'updated';
                    $gcoreStatus = (string) ($updated['status']['status'] ?? 'OK');
                }

                foreach ($updates as [$binding, $desired, $manual]) {
                    $binding->forceFill([
                        'profile_ip' => $ip,
                        'applied_sips' => $desired,
                        'manual_sips' => $manual,
                        'status' => 'ok',
                        'last_error' => null,
                        'applied_at' => now(),
                    ])->save();
                }

                $message = $changed > 0
                    ? sprintf('%d regra(s) atualizada(s) · Gcore %s', $changed, $gcoreStatus ?? 'OK')
                    : 'regras já estavam atualizadas';
                if ($orphans !== []) {
                    $message .= sprintf(' · %d regra(s) não encontrada(s)', count($orphans));
                }

                return ['profile_id' => $profileId, 'ip' => $ip, 'status' => $orphans !== [] && $changed === 0 ? 'orphan' : $status, 'rules' => $changed, 'message' => $message];
            });
        } catch (\Throwable $e) {
            foreach ($bindings as $binding) {
                if ($binding->status !== 'orphan') {
                    $binding->forceFill(['status' => 'error', 'last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();
                }
            }

            return ['profile_id' => $profileId, 'ip' => $ip, 'status' => 'error', 'rules' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Pair each binding with one ACL rule: same policy/proto/dport/sport, preferring the rule
     * that still holds the CIDRs we last wrote.
     *
     * @param list<array<string, mixed>> $acl
     * @param Collection<int, GcoreIpListBinding> $bindings
     * @return array{0: array<int, GcoreIpListBinding>, 1: list<GcoreIpListBinding>}
     */
    private function match(array $acl, Collection $bindings): array
    {
        $bySig = [];
        foreach ($acl as $idx => $rule) {
            $bySig[self::signature($rule)][] = $idx;
        }

        $matches = [];
        $orphans = [];
        foreach ($bindings as $binding) {
            $candidates = array_values(array_filter(
                $bySig[self::signature($binding->only(['policy', 'proto_list', 'dport_list', 'sport_list']))] ?? [],
                fn (int $idx) => !isset($matches[$idx]),
            ));
            if ($candidates === []) {
                $orphans[] = $binding;
                continue;
            }
            $applied = $binding->applied_sips ?? [];
            $best = $candidates[0];
            foreach ($candidates as $idx) {
                if (array_diff($applied, (array) ($acl[$idx]['sip_list'] ?? [])) === []) {
                    $best = $idx;
                    break;
                }
            }
            $matches[$best] = $binding;
        }

        return [$matches, $orphans];
    }

    /**
     * Manual IPs of a bound rule = its sip_list minus what the lists put there.
     * An IP typed by hand that also came from a list stays manual.
     *
     * @param array<string, mixed> $rule
     * @return list<string>
     */
    private function manualFrom(array $rule, GcoreIpListBinding $binding): array
    {
        $fromLists = array_diff($binding->applied_sips ?? [], $binding->manual_sips ?? []);

        return array_values(array_diff((array) ($rule['sip_list'] ?? []), $fromLists));
    }

    /** @param list<string> $a @param list<string> $b */
    private function sameSet(array $a, array $b): bool
    {
        $a = array_values(array_unique(array_map('strval', $a)));
        $b = array_values(array_unique(array_map('strval', $b)));
        sort($a);
        sort($b);

        return $a === $b;
    }

    private function client(): GcoreClient
    {
        return $this->client ??= GcoreClient::fromConfig();
    }
}
