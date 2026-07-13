<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $eggIds = DB::table('eggs')
            ->where('name', 'like', 'Plutonium%')
            ->pluck('id');

        $now = now();
        $brandingVariableIds = [];

        foreach ($eggIds as $eggId) {
            $existingId = DB::table('egg_variables')
                ->where('egg_id', $eggId)
                ->where('env_variable', 'BRANDING')
                ->orderByDesc('id')
                ->value('id');

            if ($existingId) {
                $brandingVariableIds[$eggId] = $existingId;

                continue;
            }

            $brandingVariableIds[$eggId] = DB::table('egg_variables')->insertGetId([
                'egg_id' => $eggId,
                'name' => 'Branding Check',
                'description' => 'Ativar verificação de branding HostGamer no hostname (1=sim, 0=não). Apenas admin.',
                'env_variable' => 'BRANDING',
                'default_value' => '0',
                'user_viewable' => 0,
                'user_editable' => 0,
                'rules' => 'required|boolean',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasColumn('servers', 'warn_hostname_branding')) {
            $servers = DB::table('servers')
                ->select('servers.id', 'servers.egg_id', 'servers.warn_hostname_branding', 'eggs.warn_hostname_branding as egg_warn_hostname_branding')
                ->join('eggs', 'eggs.id', '=', 'servers.egg_id')
                ->whereIn('servers.egg_id', array_keys($brandingVariableIds))
                ->get();

            foreach ($servers as $server) {
                $variableId = $brandingVariableIds[$server->egg_id] ?? null;
                if ($variableId === null) {
                    continue;
                }

                $enabled = $server->warn_hostname_branding ?? $server->egg_warn_hostname_branding ?? false;
                if (!$enabled) {
                    continue;
                }

                $exists = DB::table('server_variables')
                    ->where('server_id', $server->id)
                    ->where('variable_id', $variableId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('server_variables')->insert([
                    'server_id' => $server->id,
                    'variable_id' => $variableId,
                    'variable_value' => '1',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('warn_hostname_branding');
            });
        }

        if (Schema::hasColumn('eggs', 'warn_hostname_branding')) {
            Schema::table('eggs', function (Blueprint $table) {
                $table->dropColumn('warn_hostname_branding');
            });
        }
    }

    public function down(): void
    {
        DB::table('egg_variables')
            ->where('env_variable', 'BRANDING')
            ->delete();

        Schema::table('eggs', function (Blueprint $table) {
            $table->boolean('warn_hostname_branding')->default(false)->after('warn_slot_mismatch');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('warn_hostname_branding')->nullable()->after('warn_slot_mismatch');
        });
    }
};
