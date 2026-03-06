<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deploy_plan_variable_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deploy_plan_id');
            $table->unsignedBigInteger('egg_variable_id');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['deploy_plan_id', 'egg_variable_id'], 'deploy_plan_var_overrides_plan_var_unique');
            $table->foreign('deploy_plan_id')->references('id')->on('deploy_plans')->onDelete('cascade');
            $table->foreign('egg_variable_id')->references('id')->on('egg_variables')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deploy_plan_variable_overrides');
    }
};
