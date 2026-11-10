<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An ACL rule managed by the port sync for every egg port slot that links to it.
 *
 * mode "dport": the server ports go into dport_list.
 * mode "same_port": they go into dport_list and sport_list (Gcore crosses both lists, so
 * this drops any source→destination pair among those ports, including port = port).
 *
 * @property int $id
 * @property string $name
 * @property string $policy
 * @property string|null $proto
 * @property list<string>|null $sip_list
 * @property string $mode
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GcoreLinkedRule extends Model
{
    public const MODE_DPORT = 'dport';
    public const MODE_SAME_PORT = 'same_port';
    public const MODES = [self::MODE_DPORT, self::MODE_SAME_PORT];

    protected $table = 'gcore_linked_rules';

    protected $fillable = ['name', 'policy', 'proto', 'sip_list', 'mode'];

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

    public function modeLabel(): string
    {
        return $this->mode === self::MODE_SAME_PORT ? 'origem = destino (sport + dport)' : 'dport';
    }
}
