<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191)->nullable();
            $table->text('message');
            $table->string('type', 16)->default('info');
            $table->boolean('enabled')->default(true);
            $table->boolean('dismissible')->default(true);
            $table->string('audience', 16)->default('all');
            $table->json('placements');
            $table->json('server_pages')->nullable();
            $table->json('nest_ids')->nullable();
            $table->json('egg_ids')->nullable();
            $table->json('node_ids')->nullable();
            $table->json('location_ids')->nullable();
            $table->json('server_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_alerts');
    }
};
