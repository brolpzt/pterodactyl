<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcore_attack_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('gcore_event_id')->unique();
            $table->string('alert_type', 32)->default('ddos_alert');
            $table->string('targeted_ip', 45)->index();
            $table->timestamp('attack_start_time')->nullable()->index();
            $table->timestamp('attack_end_time')->nullable();
            $table->unsignedBigInteger('attack_power_bps')->nullable();
            $table->unsignedBigInteger('attack_power_pps')->nullable();
            $table->unsignedInteger('source_ip_count')->nullable();
            $table->boolean('advanced_stats_ready')->default(false);
            $table->boolean('detail_fetched')->default(false);
            $table->json('detail')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['targeted_ip', 'attack_start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcore_attack_events');
    }
};
