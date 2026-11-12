<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Server Boost: the external boost system reads the server list through a token URL and
        // reports each player it sends to a server. boost_hidden keeps a server out of that list.
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('boost_hidden')->default(false);
        });

        Schema::create('boost_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->nullable();
            $table->string('address', 191);
            $table->string('player_name', 64)->nullable();
            $table->string('player_ip', 45)->nullable();
            $table->string('steam_id', 64)->nullable();
            $table->string('source_ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->index(['server_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boost_connections');
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('boost_hidden');
        });
    }
};
