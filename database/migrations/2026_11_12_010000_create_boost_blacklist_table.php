<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Players the boost system must not send anywhere, read by it through the token API.
        Schema::create('boost_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16); // ip | steam_id | name
            $table->string('value', 64);
            $table->string('reason', 191)->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boost_blacklist');
    }
};
