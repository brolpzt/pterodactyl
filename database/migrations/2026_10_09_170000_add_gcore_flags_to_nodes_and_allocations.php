<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('nodes', 'gcore_enabled')) {
                $table->boolean('gcore_enabled')->default(false)->after('maintenance_mode');
            }
            if (!Schema::hasColumn('nodes', 'gcore_policy')) {
                $table->string('gcore_policy', 64)->default('allowlist')->after('gcore_enabled');
            }
        });

        Schema::table('allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('allocations', 'gcore_protected')) {
                $table->boolean('gcore_protected')->default(false)->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            if (Schema::hasColumn('nodes', 'gcore_policy')) {
                $table->dropColumn('gcore_policy');
            }
            if (Schema::hasColumn('nodes', 'gcore_enabled')) {
                $table->dropColumn('gcore_enabled');
            }
        });

        Schema::table('allocations', function (Blueprint $table) {
            if (Schema::hasColumn('allocations', 'gcore_protected')) {
                $table->dropColumn('gcore_protected');
            }
        });
    }
};
