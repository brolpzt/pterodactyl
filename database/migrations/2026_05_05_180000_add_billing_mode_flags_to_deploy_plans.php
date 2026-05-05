<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deploy_plans', function (Blueprint $table) {
            $table->boolean('enable_hourly')->default(true)->after('monthly_rate');
            $table->boolean('enable_monthly')->default(true)->after('enable_hourly');
        });
    }

    public function down(): void
    {
        Schema::table('deploy_plans', function (Blueprint $table) {
            $table->dropColumn(['enable_hourly', 'enable_monthly']);
        });
    }
};
