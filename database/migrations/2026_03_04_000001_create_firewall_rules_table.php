<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firewall_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->string('ip', 45); // supports IPv4 and IPv6
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->unique(['server_id', 'ip']);
            $table->index('server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firewall_rules');
    }
};
