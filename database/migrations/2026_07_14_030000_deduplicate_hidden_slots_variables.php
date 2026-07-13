<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $eggIds = DB::table('egg_variables')
            ->select('egg_id')
            ->where('env_variable', 'SLOTS')
            ->groupBy('egg_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('egg_id');

        foreach ($eggIds as $eggId) {
            $variables = DB::table('egg_variables')
                ->where('egg_id', $eggId)
                ->where('env_variable', 'SLOTS')
                ->orderByDesc('id')
                ->get();

            if ($variables->count() < 2) {
                continue;
            }

            $keeper = $variables->first(fn ($variable) => (bool) $variable->user_viewable)
                ?? $variables->first();

            foreach ($variables as $duplicate) {
                if ($duplicate->id === $keeper->id) {
                    continue;
                }

                $serverOverrides = DB::table('server_variables')
                    ->where('variable_id', $duplicate->id)
                    ->get();

                foreach ($serverOverrides as $override) {
                    $keeperOverride = DB::table('server_variables')
                        ->where('server_id', $override->server_id)
                        ->where('variable_id', $keeper->id)
                        ->first();

                    if ($keeperOverride === null) {
                        DB::table('server_variables')->insert([
                            'server_id' => $override->server_id,
                            'variable_id' => $keeper->id,
                            'variable_value' => $override->variable_value,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        continue;
                    }

                    if (trim((string) $keeperOverride->variable_value) === '') {
                        DB::table('server_variables')
                            ->where('id', $keeperOverride->id)
                            ->update([
                                'variable_value' => $override->variable_value,
                                'updated_at' => now(),
                            ]);
                    }
                }

                DB::table('egg_variables')->where('id', $duplicate->id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Cannot safely restore removed duplicate variables.
    }
};
