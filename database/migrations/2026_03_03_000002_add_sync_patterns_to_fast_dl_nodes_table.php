<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSyncPatternsToFastDlNodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fast_dl_nodes', function (Blueprint $table) {
            $table->text('sync_patterns')->nullable()->after('remote_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('fast_dl_nodes', function (Blueprint $table) {
            $table->dropColumn('sync_patterns');
        });
    }
}
