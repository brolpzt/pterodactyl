<?php

namespace Pterodactyl\Services\Boost;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\IpUtils;
use Pterodactyl\Models\BoostConnection;
use Pterodactyl\Models\BoostBlacklistEntry;
use Illuminate\Validation\ValidationException;

/**
 * Players the boost system must not send to any server. Entries match by IP (or CIDR range),
 * SteamID or player name (case-insensitive); the boost system reads them through the token API.
 */
class BoostBlacklistService
{
    /**
     * Validate and normalize a value for its type.
     *
     * @throws ValidationException
     */
    public static function normalize(string $type, string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '') ?? '');
        if ($value === '') {
            throw ValidationException::withMessages(['value' => 'Informe o valor.']);
        }

        switch ($type) {
            case BoostBlacklistEntry::TYPE_IP:
                return self::normalizeIp($value)
                    ?? throw ValidationException::withMessages(['value' => "IP ou faixa CIDR inválida: {$value}"]);
            case BoostBlacklistEntry::TYPE_STEAM_ID:
                if (str_contains($value, ' ') || mb_strlen($value) > 64) {
                    throw ValidationException::withMessages(['value' => "SteamID inválido: {$value}"]);
                }

                return strtoupper($value);
            case BoostBlacklistEntry::TYPE_NAME:
                if (mb_strlen($value) > 64) {
                    throw ValidationException::withMessages(['value' => 'Nome com mais de 64 caracteres.']);
                }

                return $value;
        }

        throw ValidationException::withMessages(['type' => 'Tipo inválido.']);
    }

    private static function normalizeIp(string $value): ?string
    {
        [$ip, $mask] = array_pad(explode('/', $value, 2), 2, null);
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        $ip = inet_ntop($packed);
        $bits = strlen($packed) * 8;
        if ($mask === null) {
            return $ip;
        }
        if (!ctype_digit($mask) || (int) $mask > $bits) {
            return null;
        }

        return (int) $mask === $bits ? $ip : $ip . '/' . (int) $mask;
    }

    /** Add or update an entry (same type + value updates reason and expiry). */
    public function add(string $type, string $value, ?string $reason, ?int $days, ?int $userId): BoostBlacklistEntry
    {
        $value = self::normalize($type, $value);
        $existing = $type === BoostBlacklistEntry::TYPE_NAME
            ? BoostBlacklistEntry::query()->where('type', $type)->get()->first(fn ($e) => mb_strtolower($e->value) === mb_strtolower($value))
            : BoostBlacklistEntry::query()->where('type', $type)->where('value', $value)->first();

        $entry = $existing ?? new BoostBlacklistEntry(['type' => $type, 'value' => $value, 'created_by' => $userId]);
        $entry->reason = ($reason = trim((string) $reason)) === '' ? null : mb_substr($reason, 0, 191);
        $entry->expires_at = $days ? now()->addDays($days) : null;
        $entry->save();

        return $entry;
    }

    /** @return Collection<int, BoostBlacklistEntry> */
    public function active(): Collection
    {
        return BoostBlacklistEntry::query()->active()->orderBy('id')->get();
    }

    /**
     * Entry that blocks this player, if any.
     *
     * @param Collection<int, BoostBlacklistEntry> $entries active entries
     */
    public static function match(Collection $entries, ?string $ip, ?string $steamId, ?string $name): ?BoostBlacklistEntry
    {
        $steamId = $steamId !== null ? strtoupper(trim($steamId)) : null;
        $name = $name !== null ? mb_strtolower(trim($name)) : null;

        foreach ($entries as $entry) {
            $hit = match ($entry->type) {
                BoostBlacklistEntry::TYPE_IP => $ip !== null && $ip !== '' && IpUtils::checkIp($ip, $entry->value),
                BoostBlacklistEntry::TYPE_STEAM_ID => $steamId !== null && $steamId !== '' && $steamId === $entry->value,
                BoostBlacklistEntry::TYPE_NAME => $name !== null && $name !== '' && $name === mb_strtolower($entry->value),
                default => false,
            };
            if ($hit) {
                return $entry;
            }
        }

        return null;
    }

    /** @param Collection<int, BoostBlacklistEntry> $entries */
    public static function matchConnection(Collection $entries, BoostConnection $c): ?BoostBlacklistEntry
    {
        return self::match($entries, $c->player_ip, $c->steam_id, $c->player_name);
    }

    /**
     * What the boost system reads: the entries plus one list per type.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $entries = $this->active();
        $byType = fn (string $type) => $entries->where('type', $type)->pluck('value')->values()->all();

        return [
            'count' => $entries->count(),
            'ips' => $byType(BoostBlacklistEntry::TYPE_IP),
            'steam_ids' => $byType(BoostBlacklistEntry::TYPE_STEAM_ID),
            'names' => $byType(BoostBlacklistEntry::TYPE_NAME),
            'entries' => $entries->map(fn (BoostBlacklistEntry $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'value' => $e->value,
                'reason' => $e->reason,
                'created_at' => $e->created_at->toIso8601String(),
                'expires_at' => $e->expires_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
