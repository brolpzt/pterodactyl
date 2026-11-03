<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Per-allocation Gcore ACL override (set from the Gcore profile editor).
 * Only honoured while allocations.server_id still equals gcore_override_server_id,
 * so a port handed to another server falls back to that server's egg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('allocations', 'gcore_override_policy')) {
                $table->string('gcore_override_policy', 64)->nullable()->after('gcore_protected');
            }
            if (!Schema::hasColumn('allocations', 'gcore_override_proto')) {
                $table->string('gcore_override_proto', 16)->nullable()->after('gcore_override_policy');
            }
            if (!Schema::hasColumn('allocations', 'gcore_override_server_id')) {
                $table->unsignedInteger('gcore_override_server_id')->nullable()->after('gcore_override_proto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('allocations', function (Blueprint $table) {
            foreach (['gcore_override_server_id', 'gcore_override_proto', 'gcore_override_policy'] as $column) {
                if (Schema::hasColumn('allocations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
