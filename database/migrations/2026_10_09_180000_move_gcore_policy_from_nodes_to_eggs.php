<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            if (!Schema::hasColumn('eggs', 'gcore_policy')) {
                $table->string('gcore_policy', 64)->nullable()->after('gamedig');
            }
        });

        Schema::table('nodes', function (Blueprint $table) {
            if (Schema::hasColumn('nodes', 'gcore_policy')) {
                $table->dropColumn('gcore_policy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('nodes', 'gcore_policy')) {
                $table->string('gcore_policy', 64)->default('allowlist')->after('gcore_enabled');
            }
        });

        Schema::table('eggs', function (Blueprint $table) {
            if (Schema::hasColumn('eggs', 'gcore_policy')) {
                $table->dropColumn('gcore_policy');
            }
        });
    }
};
