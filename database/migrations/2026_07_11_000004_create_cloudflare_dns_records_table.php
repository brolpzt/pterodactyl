<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudflare_dns_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->unsignedBigInteger('zone_id');
            $table->string('cloudflare_record_id', 64);
            $table->string('type', 16);
            $table->string('subdomain', 191);
            $table->string('name', 255);
            $table->string('content', 512);
            $table->unsignedInteger('ttl')->default(1);
            $table->boolean('proxied')->default(false);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('cloudflare_zones')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['zone_id', 'subdomain']);
            $table->index('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_dns_records');
    }
};
