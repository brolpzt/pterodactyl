<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_gcore_ips', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('node_id');
            $table->string('ip', 45);
            $table->timestamps();

            $table->unique(['node_id', 'ip']);
            $table->foreign('node_id')->references('id')->on('nodes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_gcore_ips');
    }
};
