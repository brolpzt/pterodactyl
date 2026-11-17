<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fast_dl_nodes', function (Blueprint $table) {
            // Encrypted Cloudflare token used only for the cache purge.
            $table->text('cloudflare_purge_token')->nullable()->after('cloudflare_zone_id');
        });
    }

    public function down(): void
    {
        Schema::table('fast_dl_nodes', function (Blueprint $table) {
            $table->dropColumn('cloudflare_purge_token');
        });
    }
};
