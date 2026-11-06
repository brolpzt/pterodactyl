<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Games read FastDL from a subfolder of the server (CS 1.6: cstrike/,
     * Plutonium T6: storage/t6/), so the URL shown to clients carries it.
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->string('fastdl_url_suffix', 255)->nullable()->after('fastdl_patterns');
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('fastdl_url_suffix');
        });
    }
};
