<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An ACL rule managed by the port sync for every egg port slot that links to it.
 *
 * The server ports go into its dport_list.
 *
 * @property int $id
 * @property string $name
 * @property string $policy
 * @property string|null $proto
 * @property list<string>|null $sip_list
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GcoreLinkedRule extends Model
{
    protected $table = 'gcore_linked_rules';

    protected $fillable = ['name', 'policy', 'proto', 'sip_list'];

    protected $casts = [
        'sip_list' => 'array',
    ];

    /** @return list<string> */
    public function protoList(): array
    {
        $proto = strtolower(trim((string) $this->proto));

        return $proto === '' || $proto === 'any' ? ['any'] : [$proto];
    }

    /** @return list<string> */
    public function sipList(): array
    {
        return array_values(array_filter(array_map(fn ($s) => trim((string) $s), $this->sip_list ?? []), fn ($s) => $s !== ''));
    }
}
