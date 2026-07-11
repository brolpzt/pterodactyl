<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloudflare_zones', function (Blueprint $table) {
            $table->string('label', 191)->default('')->after('domain');
            $table->string('public_domain', 191)->nullable()->after('label');
        });

        DB::table('cloudflare_zones')->update([
            'public_domain' => DB::raw('domain'),
            'label' => DB::raw('domain'),
        ]);

        Schema::table('cloudflare_zones', function (Blueprint $table) {
            $table->dropUnique(['domain']);
            $table->dropUnique(['zone_id']);
            $table->unique('public_domain');
        });
    }

    public function down(): void
    {
        Schema::table('cloudflare_zones', function (Blueprint $table) {
            $table->dropUnique(['public_domain']);
            $table->unique('domain');
            $table->unique('zone_id');
            $table->dropColumn(['label', 'public_domain']);
        });
    }
};
