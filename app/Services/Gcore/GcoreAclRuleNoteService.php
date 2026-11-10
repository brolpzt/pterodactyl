<?php

namespace Pterodactyl\Services\Gcore;

use Pterodactyl\Models\GcoreAclRuleNote;

/**
 * Panel-side descriptions for Gcore ACL rules.
 *
 * Rule identity = policy + proto + sport + manual sip + IP lists. Those never change by
 * themselves (the port sync only rewrites dports), so a description survives syncs.
 * When several rules share that identity, dport overlap and then position break the tie.
 */
class GcoreAclRuleNoteService
{
    public const MAX_LENGTH = 160;

    /**
     * @param array<string, mixed> $rule sip_list = manual IPs only, ip_lists = list ids
     */
    public static function ruleKey(array $rule, bool $legacy = false): string
    {
        $norm = function ($values, bool $numeric = false): string {
            $values = array_map(fn ($v) => $numeric ? (int) $v : strtolower(trim((string) $v)), (array) $values);
            $values = array_values(array_unique($values));
            sort($values);

            return implode(',', $values);
        };
        $protos = $norm($rule['proto_list'] ?? []);
        $sports = $norm($rule['sport_list'] ?? [], true);
        // "Origem = destino" rules (sport = dport, kept in step by the linked-rule sync): the
        // sport changes with every server, so key it by shape instead of by ports.
        if (!$legacy && $sports !== '' && $sports === $norm($rule['dport_list'] ?? [], true)) {
            $sports = '=dport';
        }

        return mb_substr(implode('|', [
            trim((string) ($rule['policy'] ?? '')),
            $protos === '' ? 'any' : $protos,
            $sports,
            $norm($rule['sip_list'] ?? []),
            $norm($rule['ip_lists'] ?? [], true),
        ]), 0, 191);
    }

    /**
     * Add 'description' to each rule that has one stored.
     *
     * @param list<array<string, mixed>> $acl
     *
     * @return array{acl: list<array<string, mixed>>, lost: int}
     */
    public function annotate(int $profileId, array $acl): array
    {
        $notes = GcoreAclRuleNote::query()->where('profile_id', $profileId)->get();
        if ($notes->isEmpty()) {
            return ['acl' => $acl, 'lost' => 0];
        }

        $pairs = [];
        foreach ($acl as $idx => $rule) {
            // Legacy key: notes stored before "=dport" keys existed.
            $keys = [self::ruleKey($rule), self::ruleKey($rule, true)];
            $dports = array_map('intval', (array) ($rule['dport_list'] ?? []));
            foreach ($notes as $n => $note) {
                if (!in_array($note->rule_key, $keys, true)) {
                    continue;
                }
                $pairs[] = [$idx, $n, $this->overlap($dports, $note->dport_list ?? []), abs($note->position - $idx)];
            }
        }
        usort($pairs, fn ($a, $b) => [$b[2], $a[3]] <=> [$a[2], $b[3]]);

        $usedRules = [];
        $usedNotes = [];
        foreach ($pairs as [$idx, $n]) {
            if (isset($usedRules[$idx]) || isset($usedNotes[$n])) {
                continue;
            }
            $usedRules[$idx] = $usedNotes[$n] = true;
            $acl[$idx]['description'] = $notes[$n]->description;
        }

        return ['acl' => $acl, 'lost' => $notes->count() - count($usedNotes)];
    }

    /**
     * Replace the profile's descriptions with the ones from the editor form.
     *
     * @param list<array<string, mixed>> $acl form rules (manual sip + ip_lists + description)
     */
    public function store(int $profileId, array $acl): int
    {
        GcoreAclRuleNote::query()->where('profile_id', $profileId)->delete();
        $count = 0;
        foreach ($acl as $idx => $rule) {
            $description = trim((string) ($rule['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            GcoreAclRuleNote::query()->create([
                'profile_id' => $profileId,
                'rule_key' => self::ruleKey($rule),
                'position' => $idx,
                'dport_list' => array_values(array_map('intval', (array) ($rule['dport_list'] ?? []))),
                'description' => mb_substr($description, 0, self::MAX_LENGTH),
            ]);
            ++$count;
        }

        return $count;
    }

    /** Jaccard similarity of two port sets (1.0 when both are empty). */
    private function overlap(array $a, array $b): float
    {
        $a = array_unique(array_map('intval', $a));
        $b = array_unique(array_map('intval', $b));
        if ($a === [] && $b === []) {
            return 1.0;
        }
        $union = count(array_unique(array_merge($a, $b)));

        return $union === 0 ? 0.0 : count(array_intersect($a, $b)) / $union;
    }
}
