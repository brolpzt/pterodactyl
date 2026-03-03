<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFastDlNodesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fast_dl_nodes', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->integer('location_id')->unsigned()->index();
            $table->string('fqdn');
            $table->integer('port')->unsigned()->default(22);
            $table->string('remote_path');
            $table->string('username');
            $table->text('password')->nullable();
            $table->text('private_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fast_dl_nodes');
    }
}
