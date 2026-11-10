<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $url
 * @property int $refresh_hours
 * @property bool $enabled
 * @property list<string>|null $entries
 * @property int $entry_count
 * @property string|null $entries_hash
 * @property string|null $last_status
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property \Illuminate\Support\Carbon|null $last_changed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GcoreIpList extends Model
{
    protected $table = 'gcore_ip_lists';

    protected $fillable = ['name', 'url', 'refresh_hours', 'enabled'];

    protected $casts = [
        'refresh_hours' => 'integer',
        'enabled' => 'boolean',
        'entries' => 'array',
        'entry_count' => 'integer',
        'last_checked_at' => 'datetime',
        'last_changed_at' => 'datetime',
    ];

    public static array $validationRules = [
        'name' => 'required|string|max:64',
        'url' => 'required|url|starts_with:https://,http://|max:512',
        'refresh_hours' => 'required|integer|min:1|max:720',
        'enabled' => 'boolean',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(GcoreIpListLog::class, 'gcore_ip_list_id')->orderByDesc('id');
    }

    /** @return list<string> */
    public function entryList(): array
    {
        return array_values(array_map('strval', $this->entries ?? []));
    }

    public function isDue(): bool
    {
        return $this->enabled
            && ($this->last_checked_at === null || $this->last_checked_at->lte(now()->subHours(max(1, $this->refresh_hours))));
    }

    /** @return \Illuminate\Support\Collection<int, GcoreIpListBinding> */
    public function bindings()
    {
        return GcoreIpListBinding::query()->get()->filter(fn (GcoreIpListBinding $b) => in_array($this->id, $b->listIds(), true))->values();
    }
}
