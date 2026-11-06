<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $gcore_event_id
 * @property string $alert_type
 * @property string $targeted_ip
 * @property \Illuminate\Support\Carbon|null $attack_start_time
 * @property \Illuminate\Support\Carbon|null $attack_end_time
 * @property int|null $attack_power_bps
 * @property int|null $attack_power_pps
 * @property int|null $source_ip_count
 * @property bool $advanced_stats_ready
 * @property bool $detail_fetched
 * @property array|null $detail
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GcoreAttackEvent extends Model
{
    protected $table = 'gcore_attack_events';

    protected $fillable = [
        'gcore_event_id',
        'alert_type',
        'targeted_ip',
        'attack_start_time',
        'attack_end_time',
        'attack_power_bps',
        'attack_power_pps',
        'source_ip_count',
        'advanced_stats_ready',
        'detail_fetched',
        'detail',
        'synced_at',
    ];

    protected $casts = [
        'attack_start_time' => 'datetime',
        'attack_end_time' => 'datetime',
        'attack_power_bps' => 'integer',
        'attack_power_pps' => 'integer',
        'source_ip_count' => 'integer',
        'advanced_stats_ready' => 'boolean',
        'detail_fetched' => 'boolean',
        'detail' => 'array',
        'synced_at' => 'datetime',
    ];
}
