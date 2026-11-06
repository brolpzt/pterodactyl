<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Wings watches the server files and asks for a FastDL synchronization when
     * files of the egg's FastDL types are added or changed.
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->boolean('fastdl_auto_sync')->default(true)->after('fastdl_sync_on_install');
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('fastdl_auto_sync');
        });
    }
};
