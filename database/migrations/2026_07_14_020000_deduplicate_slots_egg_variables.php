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
                ->orderBy('id')
                ->get();

            if ($variables->count() < 2) {
                continue;
            }

            $keeper = $variables->last();

            foreach ($variables->slice(0, -1) as $duplicate) {
                $serverOverrides = DB::table('server_variables')
                    ->where('variable_id', $duplicate->id)
                    ->get();

                foreach ($serverOverrides as $override) {
                    $existsOnKeeper = DB::table('server_variables')
                        ->where('server_id', $override->server_id)
                        ->where('variable_id', $keeper->id)
                        ->exists();

                    if ($existsOnKeeper) {
                        continue;
                    }

                    DB::table('server_variables')->insert([
                        'server_id' => $override->server_id,
                        'variable_id' => $keeper->id,
                        'variable_value' => $override->variable_value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
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
