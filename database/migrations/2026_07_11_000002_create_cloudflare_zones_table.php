<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloudflare_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cloudflare_account_id');
            $table->string('zone_id', 64);
            $table->string('domain', 191);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_user_create')->default(true);
            $table->boolean('default_proxied')->default(false);
            $table->timestamps();

            $table->foreign('cloudflare_account_id')->references('id')->on('cloudflare_accounts')->onDelete('cascade');
            $table->unique('domain');
            $table->unique('zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflare_zones');
    }
};
