<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Gcore work that runs on the queue (port sync per IP, IP list refresh) so admin pages
        // and server create/delete never wait for the Gcore API. One row per queued run.
        Schema::create('gcore_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->unsignedInteger('node_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedBigInteger('ip_list_id')->nullable();
            $table->string('mode', 16)->nullable();
            $table->boolean('force')->default(false);
            $table->string('label', 191);
            $table->json('reasons');
            $table->string('status', 16)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcore_sync_runs');
    }
};
