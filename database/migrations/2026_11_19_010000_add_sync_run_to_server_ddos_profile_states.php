<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('server_ddos_profile_states', function (Blueprint $table) {
            // Gcore sync that applies the last change, to show the client when it is live.
            $table->unsignedBigInteger('gcore_sync_run_id')->nullable()->after('egg_ddos_profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('server_ddos_profile_states', function (Blueprint $table) {
            $table->dropColumn('gcore_sync_run_id');
        });
    }
};
