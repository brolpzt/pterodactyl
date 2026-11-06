<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * FastDL file types live on each egg (games in one nest differ, e.g. CS 1.6
     * and CS2). Each egg starts with its nest's current value; nests.fastdl_patterns
     * is kept (unused) so this can be rolled back.
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->text('fastdl_patterns')->nullable()->after('fastdl_sync_on_install');
        });

        foreach (DB::table('nests')->whereNotNull('fastdl_patterns')->get(['id', 'fastdl_patterns']) as $nest) {
            DB::table('eggs')->where('nest_id', $nest->id)->update(['fastdl_patterns' => $nest->fastdl_patterns]);
        }
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('fastdl_patterns');
        });
    }
};
