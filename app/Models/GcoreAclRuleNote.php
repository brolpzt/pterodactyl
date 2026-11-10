<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $rule_key
 * @property int $position
 * @property list<int> $dport_list
 * @property string $description
 */
class GcoreAclRuleNote extends Model
{
    protected $table = 'gcore_acl_rule_notes';

    protected $guarded = ['id'];

    protected $casts = [
        'profile_id' => 'integer',
        'position' => 'integer',
        'dport_list' => 'array',
    ];
}
