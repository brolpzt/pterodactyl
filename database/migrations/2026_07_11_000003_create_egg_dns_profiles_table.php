<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_dns_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('egg_id');
            $table->boolean('enabled')->default(false);
            $table->json('allowed_types')->nullable();
            $table->string('default_type', 16)->default('A');
            $table->unsignedSmallInteger('max_records_per_server')->default(3);
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('cascade');
            $table->unique('egg_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egg_dns_profiles');
    }
};
