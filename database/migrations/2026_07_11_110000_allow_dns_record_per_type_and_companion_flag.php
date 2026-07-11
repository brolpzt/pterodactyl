<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cloudflare_dns_records', 'is_companion')) {
            Schema::table('cloudflare_dns_records', function (Blueprint $table) {
                $table->boolean('is_companion')->default(false)->after('proxied');
            });
        }

        Schema::table('cloudflare_dns_records', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
        });

        Schema::table('cloudflare_dns_records', function (Blueprint $table) {
            $table->dropUnique(['zone_id', 'subdomain']);
            $table->unique(['zone_id', 'subdomain', 'type']);
            $table->foreign('zone_id')->references('id')->on('cloudflare_zones')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('cloudflare_dns_records', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
        });

        Schema::table('cloudflare_dns_records', function (Blueprint $table) {
            $table->dropUnique(['zone_id', 'subdomain', 'type']);
            $table->unique(['zone_id', 'subdomain']);
            $table->foreign('zone_id')->references('id')->on('cloudflare_zones')->onDelete('cascade');
        });

        if (Schema::hasColumn('cloudflare_dns_records', 'is_companion')) {
            Schema::table('cloudflare_dns_records', function (Blueprint $table) {
                $table->dropColumn('is_companion');
            });
        }
    }
};
