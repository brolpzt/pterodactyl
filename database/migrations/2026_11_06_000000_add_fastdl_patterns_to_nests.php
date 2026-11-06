<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * FastDL sync patterns per nest (each game has its own folders and file
     * types). Empty means "use the FastDL node's patterns".
     */
    public function up(): void
    {
        Schema::table('nests', function (Blueprint $table) {
            $table->text('fastdl_patterns')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('nests', function (Blueprint $table) {
            $table->dropColumn('fastdl_patterns');
        });
    }
};
