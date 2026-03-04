<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAddonCategoriesAndUpdateAddonsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('addon_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('egg_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->onDelete('cascade');
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('egg_id');
            $table->boolean('reinstall_server')->default(false)->after('container_image');

            $table->foreign('category_id')->references('id')->on('addon_categories')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'reinstall_server']);
        });

        Schema::dropIfExists('addon_categories');
    }
}
