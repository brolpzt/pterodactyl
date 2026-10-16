<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egg_forced_commands', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('egg_id');
            $table->string('env_variable', 191);
            $table->string('command_template', 191);
            $table->boolean('enabled')->default(true);
            $table->boolean('hide_output')->default(true);
            $table->unsignedInteger('interval_seconds')->default(60);
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
            $table->unique(['egg_id', 'env_variable', 'command_template'], 'egg_forced_commands_unique');
        });

        Schema::create('server_forced_command_overrides', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->unsignedInteger('egg_forced_command_id');
            $table->boolean('enabled')->nullable();
            $table->boolean('hide_output')->nullable();
            $table->unsignedInteger('interval_seconds')->nullable();
            $table->timestamp('last_forced_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('egg_forced_command_id', 'sfc_override_forced_cmd_fk')
                ->references('id')
                ->on('egg_forced_commands')
                ->cascadeOnDelete();
            $table->unique(['server_id', 'egg_forced_command_id'], 'sfc_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_forced_command_overrides');
        Schema::dropIfExists('egg_forced_commands');
    }
};
