<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('nodes', 'nftables_enabled')) {
                $table->boolean('nftables_enabled')->default(false)->after('gcore_enabled');
            }
        });

        Schema::table('allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('allocations', 'nftables_managed')) {
                $table->boolean('nftables_managed')->default(false)->after('gcore_protected');
            }
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            if (Schema::hasColumn('nodes', 'nftables_enabled')) {
                $table->dropColumn('nftables_enabled');
            }
        });

        Schema::table('allocations', function (Blueprint $table) {
            if (Schema::hasColumn('allocations', 'nftables_managed')) {
                $table->dropColumn('nftables_managed');
            }
        });
    }
};
