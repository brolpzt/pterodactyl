<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Whether Wings starts the server once the addon script finishes successfully.
     */
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->boolean('start_after_install')->default(false)->after('reinstall_server');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('start_after_install');
        });
    }
};
