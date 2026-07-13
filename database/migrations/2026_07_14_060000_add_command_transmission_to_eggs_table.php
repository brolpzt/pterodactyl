<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCommandTransmissionToEggsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->string('command_transmission_type', 50)->default('stdin')->after('force_outgoing_ip');
            $table->string('rcon_protocol', 50)->nullable()->after('command_transmission_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn(['command_transmission_type', 'rcon_protocol']);
        });
    }
}
