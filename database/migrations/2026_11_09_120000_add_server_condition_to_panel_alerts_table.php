<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_alerts', function (Blueprint $table) {
            $table->string('server_condition', 32)->nullable()->after('server_ids');
        });
    }

    public function down(): void
    {
        Schema::table('panel_alerts', function (Blueprint $table) {
            $table->dropColumn('server_condition');
        });
    }
};
