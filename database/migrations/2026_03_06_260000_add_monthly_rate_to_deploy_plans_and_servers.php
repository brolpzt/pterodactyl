<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deploy_plans', function (Blueprint $table) {
            $table->decimal('monthly_rate', 10, 2)->nullable()->after('hourly_rate');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->decimal('monthly_rate', 12, 2)->nullable()->after('hourly_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deploy_plans', function (Blueprint $table) {
            $table->dropColumn('monthly_rate');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('monthly_rate');
        });
    }
};
