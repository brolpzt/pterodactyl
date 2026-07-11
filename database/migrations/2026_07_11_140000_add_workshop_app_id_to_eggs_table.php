<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->unsignedInteger('workshop_app_id')->nullable()->after('gamedig');
            $table->string('workshop_sync_driver', 32)->nullable()->after('workshop_app_id');
        });
    }

    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn(['workshop_app_id', 'workshop_sync_driver']);
        });
    }
};
