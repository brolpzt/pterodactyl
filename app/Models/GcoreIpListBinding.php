<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $profile_id
 * @property string|null $profile_ip
 * @property string $policy
 * @property list<string> $proto_list
 * @property list<int> $dport_list
 * @property list<int> $sport_list
 * @property list<int> $list_ids
 * @property list<string> $manual_sips
 * @property list<string> $applied_sips
 * @property string $status
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $applied_at
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property int|null $verify_checked
 * @property int|null $verify_missing
 * @property string|null $verify_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GcoreIpListBinding extends Model
{
    protected $table = 'gcore_ip_list_bindings';

    protected $guarded = ['id'];

    protected $casts = [
        'profile_id' => 'integer',
        'proto_list' => 'array',
        'dport_list' => 'array',
        'sport_list' => 'array',
        'list_ids' => 'array',
        'manual_sips' => 'array',
        'applied_sips' => 'array',
        'applied_at' => 'datetime',
        'verified_at' => 'datetime',
        'verify_checked' => 'integer',
        'verify_missing' => 'integer',
    ];

    /** @return list<int> */
    public function listIds(): array
    {
        return array_values(array_map('intval', $this->list_ids ?? []));
    }
}
