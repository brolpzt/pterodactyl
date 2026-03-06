<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $plans = config('deploy.plans', []);
        if (empty($plans)) {
            return;
        }

        foreach ($plans as $config) {
            $eggId = $config['egg_id'] ?? null;
            if (!$eggId || !DB::table('eggs')->where('id', $eggId)->exists()) {
                continue;
            }

            if (DB::table('deploy_plans')->where('egg_id', $eggId)->where('name', $config['name'])->exists()) {
                continue;
            }

            DB::table('deploy_plans')->insert([
                'egg_id' => $eggId,
                'name' => $config['name'],
                'memory' => $config['memory'],
                'disk' => $config['disk'],
                'cpu' => $config['cpu'],
                'swap' => $config['swap'] ?? 0,
                'io' => $config['io'] ?? 500,
                'hourly_rate' => $config['hourly_rate'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No rollback - we don't want to delete user-created plans
    }
};
