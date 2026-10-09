<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            if (!Schema::hasColumn('eggs', 'port_range_start')) {
                $table->unsignedSmallInteger('port_range_start')->nullable()->after('port_slots');
            }
            if (!Schema::hasColumn('eggs', 'port_range_end')) {
                $table->unsignedSmallInteger('port_range_end')->nullable()->after('port_range_start');
            }
            if (!Schema::hasColumn('eggs', 'port_step')) {
                $table->unsignedTinyInteger('port_step')->default(1)->after('port_range_end');
            }
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            foreach (['port_step', 'port_range_end', 'port_range_start'] as $column) {
                if (Schema::hasColumn('eggs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
