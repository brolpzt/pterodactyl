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
        Schema::create('ticket_attachments', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedInteger('ticket_message_id');
            $blueprint->string('filename');
            $blueprint->string('hash');
            $blueprint->string('mime_type');
            $blueprint->unsignedInteger('size');
            $blueprint->timestamps();

            $blueprint->foreign('ticket_message_id')->references('id')->on('ticket_messages')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
