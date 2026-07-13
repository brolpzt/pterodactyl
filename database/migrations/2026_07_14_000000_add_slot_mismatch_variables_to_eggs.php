<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const SLOTS_ENV_CANDIDATES = [
        'MAX_CLIENTS',
        'MAX_PLAYERS',
        'SERVER_MAX_PLAYERS',
        'SLOTS',
    ];

    /** @var list<string> */
    private const SLOT_MISMATCH_VARIABLES = [
        'SLOTS_ENV_VARIABLE',
        'WARN_SLOT_MISMATCH',
    ];

    public function up(): void
    {
        $eggIds = DB::table('egg_variables')
            ->whereIn('env_variable', self::SLOTS_ENV_CANDIDATES)
            ->distinct()
            ->pluck('egg_id');

        $now = now();

        foreach ($eggIds as $eggId) {
            $slotsEnvDefault = $this->resolveSlotsEnvDefault((int) $eggId);

            if ($slotsEnvDefault === null) {
                continue;
            }

            $this->insertVariableIfMissing((int) $eggId, [
                'name' => 'Slots Env Variable',
                'description' => 'Variável de ambiente com o limite de slots no painel (apenas admin).',
                'env_variable' => 'SLOTS_ENV_VARIABLE',
                'default_value' => $slotsEnvDefault,
                'user_viewable' => 0,
                'user_editable' => 0,
                'rules' => 'required|string|max:64',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->insertVariableIfMissing((int) $eggId, [
                'name' => 'Warn Slot Mismatch',
                'description' => 'Exibir aviso quando a query reportar mais slots que o limite do painel (apenas admin).',
                'env_variable' => 'WARN_SLOT_MISMATCH',
                'default_value' => '1',
                'user_viewable' => 0,
                'user_editable' => 0,
                'rules' => 'required|boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('egg_variables')
            ->whereIn('env_variable', self::SLOT_MISMATCH_VARIABLES)
            ->delete();
    }

    private function resolveSlotsEnvDefault(int $eggId): ?string
    {
        foreach (self::SLOTS_ENV_CANDIDATES as $candidate) {
            $exists = DB::table('egg_variables')
                ->where('egg_id', $eggId)
                ->where('env_variable', $candidate)
                ->exists();

            if ($exists) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function insertVariableIfMissing(int $eggId, array $attributes): void
    {
        $exists = DB::table('egg_variables')
            ->where('egg_id', $eggId)
            ->where('env_variable', $attributes['env_variable'])
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('egg_variables')->insert(array_merge(['egg_id' => $eggId], $attributes));
    }
};
