<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            if (!Schema::hasColumn('eggs', 'port_slots')) {
                $table->json('port_slots')->nullable()->after('gcore_proto');
            }
        });

        Schema::table('allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('allocations', 'port_env')) {
                $table->string('port_env', 191)->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            if (Schema::hasColumn('allocations', 'port_env')) {
                $table->dropColumn('port_env');
            }
        });

        Schema::table('eggs', function (Blueprint $table) {
            if (Schema::hasColumn('eggs', 'port_slots')) {
                $table->dropColumn('port_slots');
            }
        });
    }
};
