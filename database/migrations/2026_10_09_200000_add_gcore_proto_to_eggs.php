<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            if (!Schema::hasColumn('eggs', 'gcore_proto')) {
                $table->string('gcore_proto', 16)->nullable()->after('gcore_policy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            if (Schema::hasColumn('eggs', 'gcore_proto')) {
                $table->dropColumn('gcore_proto');
            }
        });
    }
};
