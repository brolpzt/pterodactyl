<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('xdp_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('node_id');
            $table->string('trigger', 20);
            $table->string('reason', 191)->nullable();
            $table->boolean('ok');
            $table->unsignedInteger('ports')->default(0);
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('removed')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->json('changes')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['node_id', 'created_at']);
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xdp_sync_logs');
    }
};
