<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('egg_dns_profiles', function (Blueprint $table) {
            $table->string('srv_service', 32)->nullable()->after('max_records_per_server');
            $table->string('srv_protocol', 16)->nullable()->after('srv_service');
            $table->unsignedSmallInteger('srv_priority')->default(0)->after('srv_protocol');
            $table->unsignedSmallInteger('srv_weight')->default(5)->after('srv_priority');
        });

        Schema::table('cloudflare_dns_records', function (Blueprint $table) {
            $table->string('srv_service', 32)->nullable()->after('proxied');
            $table->string('srv_protocol', 16)->nullable()->after('srv_service');
            $table->unsignedSmallInteger('srv_port')->nullable()->after('srv_protocol');
            $table->unsignedSmallInteger('srv_priority')->nullable()->after('srv_port');
            $table->unsignedSmallInteger('srv_weight')->nullable()->after('srv_priority');
        });
    }

    public function down(): void
    {
        Schema::table('cloudflare_dns_records', function (Blueprint $table) {
            $table->dropColumn(['srv_service', 'srv_protocol', 'srv_port', 'srv_priority', 'srv_weight']);
        });

        Schema::table('egg_dns_profiles', function (Blueprint $table) {
            $table->dropColumn(['srv_service', 'srv_protocol', 'srv_priority', 'srv_weight']);
        });
    }
};
