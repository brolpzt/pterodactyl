<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAddonsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('egg_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('script');
            $table->string('container_image');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('cascade');
        });

        Schema::create('addon_server', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('addon_id');
            $table->unsignedInteger('server_id');
            $table->timestamp('installed_at')->useCurrent();

            $table->foreign('addon_id')->references('id')->on('addons')->onDelete('cascade');
            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_server');
        Schema::dropIfExists('addons');
    }
}
