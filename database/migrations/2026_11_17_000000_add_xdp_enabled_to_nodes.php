<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('nodes', 'xdp_enabled')) {
            Schema::table('nodes', function (Blueprint $table) {
                $table->boolean('xdp_enabled')->default(false)->after('nftables_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('nodes', 'xdp_enabled')) {
            Schema::table('nodes', function (Blueprint $table) {
                $table->dropColumn('xdp_enabled');
            });
        }
    }
};
