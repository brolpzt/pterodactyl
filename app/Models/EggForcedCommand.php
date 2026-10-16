<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $egg_id
 * @property string $env_variable
 * @property string $command_template
 * @property bool $enabled
 * @property bool $hide_output
 * @property int $interval_seconds
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property Egg $egg
 * @property \Illuminate\Database\Eloquent\Collection|ServerForcedCommandOverride[] $serverOverrides
 */
class EggForcedCommand extends Model
{
    protected $table = 'egg_forced_commands';

    protected $fillable = [
        'egg_id',
        'env_variable',
        'command_template',
        'enabled',
        'hide_output',
        'interval_seconds',
    ];

    protected $casts = [
        'egg_id' => 'integer',
        'enabled' => 'boolean',
        'hide_output' => 'boolean',
        'interval_seconds' => 'integer',
    ];

    public static array $validationRules = [
        'egg_id' => 'required|integer|exists:eggs,id',
        'env_variable' => 'required|string|regex:/^[\w]{1,191}$/',
        'command_template' => 'required|string|max:191',
        'enabled' => 'sometimes|boolean',
        'hide_output' => 'sometimes|boolean',
        'interval_seconds' => 'sometimes|integer|min:15|max:3600',
    ];

    protected $attributes = [
        'enabled' => true,
        'hide_output' => true,
        'interval_seconds' => 60,
    ];

    /**
     * @return BelongsTo<Egg, $this>
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * @return HasMany<ServerForcedCommandOverride, $this>
     */
    public function serverOverrides(): HasMany
    {
        return $this->hasMany(ServerForcedCommandOverride::class);
    }

    /**
     * Build the console command by substituting the ENV value into the template.
     * Supports {{VALUE}}, {{value}} and {{ENV_NAME}} placeholders.
     */
    public function buildCommand(string $value): string
    {
        $template = trim($this->command_template);
        $replacements = [
            '{{VALUE}}' => $value,
            '{{value}}' => $value,
            '{{' . $this->env_variable . '}}' => $value,
        ];

        $command = str_replace(array_keys($replacements), array_values($replacements), $template);

        // If the template has no placeholder, append the value (e.g. "sys_ticrate" → "sys_ticrate 1100").
        if ($command === $template && !str_contains($template, '{{')) {
            $command = rtrim($template) . ' ' . $value;
        }

        return trim($command);
    }
}
