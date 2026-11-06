<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Whether servers of the egg synchronize FastDL right after a successful
     * install/reinstall (on by default: that was the previous behavior).
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->boolean('fastdl_sync_on_install')->default(true)->after('force_outgoing_ip');
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('fastdl_sync_on_install');
        });
    }
};
