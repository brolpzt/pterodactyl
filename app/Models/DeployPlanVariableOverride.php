<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $deploy_plan_id
 * @property int $egg_variable_id
 * @property string|null $value
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property DeployPlan $deployPlan
 * @property EggVariable $eggVariable
 */
class DeployPlanVariableOverride extends Model
{
    protected $table = 'deploy_plan_variable_overrides';

    protected $fillable = [
        'deploy_plan_id',
        'egg_variable_id',
        'value',
    ];

    protected $casts = [
        'deploy_plan_id' => 'integer',
        'egg_variable_id' => 'integer',
    ];

    public function deployPlan(): BelongsTo
    {
        return $this->belongsTo(DeployPlan::class);
    }

    public function eggVariable(): BelongsTo
    {
        return $this->belongsTo(EggVariable::class, 'egg_variable_id');
    }
}
