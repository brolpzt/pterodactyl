<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fast_dl_nodes', function (Blueprint $table) {
            $table->unsignedBigInteger('cloudflare_zone_id')->nullable()->after('public_url');
            $table->foreign('cloudflare_zone_id')->references('id')->on('cloudflare_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fast_dl_nodes', function (Blueprint $table) {
            $table->dropForeign(['cloudflare_zone_id']);
            $table->dropColumn('cloudflare_zone_id');
        });
    }
};
