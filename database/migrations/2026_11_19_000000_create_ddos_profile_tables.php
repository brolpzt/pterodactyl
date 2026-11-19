<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Per egg: whether clients may pick their game port's Gcore policy, and how often.
        Schema::create('egg_ddos_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('egg_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('cooldown_minutes')->default(30);
            $table->timestamps();

            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });

        // The options a client can pick: a Gcore policy behind a client-facing name and notice.
        Schema::create('egg_ddos_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('egg_id');
            $table->string('policy', 64);
            $table->string('label', 64);
            $table->string('alert_type', 16)->default('info');
            $table->string('alert_title', 191)->nullable();
            $table->text('alert_message')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['egg_id', 'sort_order']);
            $table->foreign('egg_id')->references('id')->on('eggs')->cascadeOnDelete();
        });

        // Per server: the option applied through this feature, the client's cooldown and the admin lock.
        Schema::create('server_ddos_profile_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->unsignedBigInteger('egg_ddos_profile_id')->nullable();
            $table->timestamp('client_changed_at')->nullable();
            // Hidden by default: the admin shows the picker per server.
            $table->boolean('client_locked')->default(true);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('egg_ddos_profile_id')->references('id')->on('egg_ddos_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_ddos_profile_states');
        Schema::dropIfExists('egg_ddos_profiles');
        Schema::dropIfExists('egg_ddos_settings');
    }
};
