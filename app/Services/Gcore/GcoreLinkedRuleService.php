<?php

namespace Pterodactyl\Services\Gcore;

use Pterodactyl\Models\Egg;
use Illuminate\Support\Collection;
use Pterodactyl\Models\GcoreLinkedRule;
use Pterodactyl\Services\Gcore\IpLists\IpListParser;

/**
 * Linked rules: ACL rules that egg port slots opt into ("também incluir em"). The port sync
 * keeps each one's ports equal to the ports of the servers whose slot links to it, plus any
 * manual (non-panel) port already on the rule.
 *
 * Gcore rules have no id, so a linked rule is found in a profile by its shape: policy, proto,
 * source IPs and an empty sport_list. A rule with an empty dport
 * never matches (empty = every port), and a linked rule that no egg uses is never touched.
 */
class GcoreLinkedRuleService
{
    public const MARK = '__linked';

    /**
     * Linked rules referenced by at least one egg port slot, keyed by id.
     *
     * @return Collection<int, GcoreLinkedRule>
     */
    public function inUse(): Collection
    {
        $ids = array_keys($this->usage());

        return $ids === []
            ? collect()
            : GcoreLinkedRule::query()->whereIn('id', $ids)->orderBy('id')->get()->keyBy('id');
    }

    /**
     * @return array<int, list<string>> linked rule id => ["Egg (slot)", ...]
     */
    public function usage(): array
    {
        $out = [];
        foreach (Egg::query()->whereNotNull('port_slots')->get(['id', 'name', 'port_slots']) as $egg) {
            foreach ((array) $egg->port_slots as $slot) {
                foreach ((array) ($slot['gcore_links'] ?? []) as $id) {
                    $out[(int) $id][] = $egg->name . ' (' . ($slot['env_variable'] ?? '?') . ')';
                }
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $rule */
    public static function matches(array $rule, GcoreLinkedRule $def): bool
    {
        if (trim((string) ($rule['policy'] ?? '')) !== $def->policy) {
            return false;
        }

        $protos = array_values(array_unique(array_filter(array_map(
            fn ($p) => strtolower(trim((string) $p)),
            (array) ($rule['proto_list'] ?? []),
        ))));
        sort($protos);
        if ($protos === [] || in_array('any', $protos, true)) {
            $protos = ['any'];
        }
        if ($protos !== $def->protoList()) {
            return false;
        }

        $dports = self::ports($rule['dport_list'] ?? []);
        if ($dports === []) {
            return false;
        }
        $sports = self::ports($rule['sport_list'] ?? []);
        if ($sports !== []) {
            return false;
        }

        return self::normalizeSips((array) ($rule['sip_list'] ?? [])) === self::normalizeSips($def->sipList());
    }

    /**
     * Tag the first rule matching each linked rule with MARK => id.
     *
     * @param list<array<string, mixed>> $acl
     * @param Collection<int, GcoreLinkedRule> $defs
     *
     * @return list<array<string, mixed>>
     */
    public function mark(array $acl, Collection $defs): array
    {
        foreach ($defs as $def) {
            foreach ($acl as $i => $rule) {
                if (!isset($rule[self::MARK]) && self::matches($rule, $def)) {
                    $acl[$i][self::MARK] = $def->id;
                    break;
                }
            }
        }

        return $acl;
    }

    /**
     * Set each marked rule's ports to the desired ones (keeping manual ports), drop rules left
     * without ports (an empty dport would mean every port) and create missing rules at the top.
     *
     * @param list<array<string, mixed>> $acl rules marked by mark()
     * @param Collection<int, GcoreLinkedRule> $defs
     * @param array<int, list<int>> $desired linked rule id => server ports on this IP
     * @param list<int> $panelPorts every panel allocation port on this IP
     *
     * @return array{acl: list<array<string, mixed>>, changes: list<string>}
     */
    public function apply(array $acl, Collection $defs, array $desired, array $panelPorts): array
    {
        $panel = array_fill_keys(array_map('intval', $panelPorts), true);
        $out = [];
        $changes = [];
        $found = [];

        foreach ($acl as $rule) {
            $id = $rule[self::MARK] ?? null;
            unset($rule[self::MARK]);
            $def = $id === null ? null : $defs->get($id);
            if ($def === null) {
                $out[] = $rule;
                continue;
            }
            $found[$def->id] = true;

            $current = self::ports($rule['dport_list'] ?? []);
            $manual = array_values(array_filter($current, fn (int $p) => !isset($panel[$p])));
            $final = array_values(array_unique(array_merge($manual, $desired[$def->id] ?? [])));
            sort($final);

            if ($final === []) {
                $changes[] = "regra vinculada \"{$def->name}\" removida (nenhum servidor usa)";
                continue;
            }
            if ($final !== $current) {
                $changes[] = $this->describeChange($def, $current, $final);
            }
            $rule['dport_list'] = $final;
            $out[] = $rule;
        }

        $new = [];
        foreach ($defs as $def) {
            $ports = array_values(array_unique(array_map('intval', $desired[$def->id] ?? [])));
            sort($ports);
            if (isset($found[$def->id]) || $ports === []) {
                continue;
            }
            $new[] = [
                'policy' => $def->policy,
                'sip_list' => $def->sipList(),
                'dport_list' => $ports,
                'proto_list' => $def->protoList(),
                'sport_list' => [],
            ];
            $changes[] = "regra vinculada \"{$def->name}\" criada no topo: " . implode(',', $ports);
        }

        return ['acl' => array_values(array_merge($new, $out)), 'changes' => $changes];
    }

    /**
     * Editor badges: rule index => linked rule name.
     *
     * @param list<array<string, mixed>> $acl
     *
     * @return array<int, string>
     */
    public function describe(array $acl): array
    {
        $defs = $this->inUse();
        if ($defs->isEmpty()) {
            return [];
        }
        $out = [];
        foreach ($this->mark($acl, $defs) as $i => $rule) {
            if (isset($rule[self::MARK])) {
                $out[$i] = $defs->get($rule[self::MARK])->name;
            }
        }

        return $out;
    }

    /** @return list<string> IPv4 by address range (so /32 and bare IPs compare equal), then the rest */
    public static function normalizeSips(array $sips): array
    {
        $sips = array_values(array_unique(array_filter(array_map(fn ($s) => strtolower(trim((string) $s)), $sips), fn ($s) => $s !== '')));
        $ipv4 = array_values(array_filter($sips, fn ($s) => IpListParser::parseIpv4($s) !== null));
        $other = array_values(array_diff($sips, $ipv4));
        sort($other);

        return array_merge(IpListParser::aggregate($ipv4), $other);
    }

    /** @return list<int> sorted unique ports 1–65535 */
    public static function ports(mixed $values): array
    {
        $out = [];
        foreach ((array) $values as $v) {
            if (is_int($v) || (is_string($v) && ctype_digit($v))) {
                $p = (int) $v;
                if ($p >= 1 && $p <= 65535) {
                    $out[$p] = true;
                }
            }
        }
        $list = array_keys($out);
        sort($list);

        return $list;
    }

    private function describeChange(GcoreLinkedRule $def, array $current, array $final): string
    {
        $added = array_diff($final, $current);
        $removed = array_diff($current, $final);
        $parts = [];
        if ($added) {
            $parts[] = '+' . implode(',', $added);
        }
        if ($removed) {
            $parts[] = '-' . implode(',', $removed);
        }

        return "regra vinculada \"{$def->name}\": " . implode(' ', $parts);
    }
}
