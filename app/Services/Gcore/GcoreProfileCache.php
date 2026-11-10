<?php

namespace Pterodactyl\Services\Gcore;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Last known copy of each Gcore profile (and of the profile list) so admin pages render
 * instantly and revalidate against the API in the background. Written through by GcoreClient
 * on every GET/PUT; never used as the base of a PUT (saves always re-read the profile).
 */
class GcoreProfileCache
{
    private const LIST_KEY = 'gcore:profiles';
    private const PROFILE_KEY = 'gcore:profile:';

    /** @param list<array<string, mixed>> $profiles */
    public function putList(array $profiles): void
    {
        $this->write(self::LIST_KEY, $profiles);
        foreach ($profiles as $profile) {
            // The list endpoint returns full profiles; seed the per-profile entries too.
            if (isset($profile['id'], $profile['fields']) && is_array($profile['fields'])) {
                $this->put($profile);
            }
        }
    }

    /** @return array{data: list<array<string, mixed>>, fetched_at: CarbonImmutable}|null */
    public function list(): ?array
    {
        return $this->read(self::LIST_KEY);
    }

    /** @param array<string, mixed> $profile */
    public function put(array $profile): void
    {
        if (!isset($profile['id'])) {
            return;
        }
        $this->write(self::PROFILE_KEY . (int) $profile['id'], $profile);
    }

    /** @return array{data: array<string, mixed>, fetched_at: CarbonImmutable}|null */
    public function get(int $id): ?array
    {
        return $this->read(self::PROFILE_KEY . $id);
    }

    public function forget(int $id): void
    {
        try {
            Cache::forget(self::PROFILE_KEY . $id);
        } catch (\Throwable) {
        }
    }

    /**
     * After a successful PUT: the fields we sent are now the profile's values. Merge them
     * into the cached copy (matched by base_field); without a cached copy, drop the entry.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $response
     */
    public function applyUpdate(int $id, array $payload, array $response): void
    {
        $cached = $this->get($id);
        if ($cached === null) {
            $this->forget($id);

            return;
        }

        $profile = $cached['data'];
        $sent = [];
        foreach ((array) ($payload['fields'] ?? []) as $field) {
            $sent[(int) ($field['base_field'] ?? 0)] = $field['field_value'] ?? null;
        }
        $matched = 0;
        foreach ((array) ($profile['fields'] ?? []) as $i => $field) {
            $base = (int) ($field['base_field'] ?? 0);
            if (array_key_exists($base, $sent)) {
                $profile['fields'][$i]['field_value'] = $sent[$base];
                ++$matched;
            }
        }
        if ($matched !== count($sent)) {
            $this->forget($id);

            return;
        }
        if (isset($response['status'])) {
            $profile['status'] = $response['status'];
        }
        $this->put($profile);
    }

    /**
     * Fingerprint of what the editor shows (ACL, GEOIP, rate limiters), used to notice that the
     * profile changed on Gcore since a page was rendered.
     *
     * @param array<string, mixed> $profile
     */
    public static function formHash(array $profile): string
    {
        return sha1(json_encode(GcoreAclHelper::extractProfileFormData($profile), JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function write(string $key, array $data): void
    {
        try {
            Cache::forever($key, ['data' => $data, 'fetched_at' => CarbonImmutable::now()->toIso8601String()]);
        } catch (\Throwable) {
            // A cache outage must never break a Gcore call.
        }
    }

    private function read(string $key): ?array
    {
        try {
            $entry = Cache::get($key);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($entry) || !is_array($entry['data'] ?? null) || !is_string($entry['fetched_at'] ?? null)) {
            return null;
        }

        return ['data' => $entry['data'], 'fetched_at' => CarbonImmutable::parse($entry['fetched_at'])];
    }
}
