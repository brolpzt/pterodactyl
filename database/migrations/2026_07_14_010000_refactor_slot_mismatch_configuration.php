<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const REMOVED_EGG_VARIABLES = [
        'WARN_SLOT_MISMATCH',
        'SLOTS_ENV_VARIABLE',
    ];

    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->boolean('warn_slot_mismatch')->default(false)->after('gamedig');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('warn_slot_mismatch')->nullable()->after('fastdl_enabled');
        });

        DB::table('egg_variables')
            ->whereIn('env_variable', self::REMOVED_EGG_VARIABLES)
            ->delete();

        DB::table('egg_variables')
            ->where('env_variable', 'MAX_CLIENTS')
            ->update([
                'env_variable' => 'SLOTS',
                'name' => 'Slots',
                'updated_at' => now(),
            ]);

        foreach (DB::table('eggs')->select(['id', 'startup'])->get() as $egg) {
            if (!str_contains($egg->startup, '{{MAX_CLIENTS}}')) {
                continue;
            }

            DB::table('eggs')->where('id', $egg->id)->update([
                'startup' => str_replace('{{MAX_CLIENTS}}', '{{SLOTS}}', $egg->startup),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('servers')->select(['id', 'startup'])->get() as $server) {
            if (!str_contains($server->startup, '{{MAX_CLIENTS}}')) {
                continue;
            }

            DB::table('servers')->where('id', $server->id)->update([
                'startup' => str_replace('{{MAX_CLIENTS}}', '{{SLOTS}}', $server->startup),
                'updated_at' => now(),
            ]);
        }

        DB::table('eggs')
            ->where('name', 'like', 'Plutonium%')
            ->update([
                'warn_slot_mismatch' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('egg_variables')
            ->where('env_variable', 'SLOTS')
            ->update([
                'env_variable' => 'MAX_CLIENTS',
                'name' => 'Max Clients',
                'updated_at' => now(),
            ]);

        foreach (DB::table('eggs')->select(['id', 'startup'])->get() as $egg) {
            if (!str_contains($egg->startup, '{{SLOTS}}')) {
                continue;
            }

            DB::table('eggs')->where('id', $egg->id)->update([
                'startup' => str_replace('{{SLOTS}}', '{{MAX_CLIENTS}}', $egg->startup),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('servers')->select(['id', 'startup'])->get() as $server) {
            if (!str_contains($server->startup, '{{SLOTS}}')) {
                continue;
            }

            DB::table('servers')->where('id', $server->id)->update([
                'startup' => str_replace('{{SLOTS}}', '{{MAX_CLIENTS}}', $server->startup),
                'updated_at' => now(),
            ]);
        }

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('warn_slot_mismatch');
        });

        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('warn_slot_mismatch');
        });
    }
};
