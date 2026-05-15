<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddS3StorageToFastDlNodesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fast_dl_nodes', function (Blueprint $table) {
            $table->string('storage_type', 16)->default('ssh')->after('location_id');
            $table->string('bucket')->nullable()->after('remote_path');
            $table->string('endpoint')->nullable()->after('bucket');
            $table->string('region', 64)->nullable()->default('auto')->after('endpoint');
            $table->text('access_key')->nullable()->after('region');
            $table->text('secret_key')->nullable()->after('access_key');
            $table->boolean('use_path_style_endpoint')->default(false)->after('secret_key');
            $table->string('public_url')->nullable()->after('use_path_style_endpoint');

            $table->string('username')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fast_dl_nodes', function (Blueprint $table) {
            $table->dropColumn([
                'storage_type',
                'bucket',
                'endpoint',
                'region',
                'access_key',
                'secret_key',
                'use_path_style_endpoint',
                'public_url',
            ]);

            $table->string('username')->nullable(false)->change();
        });
    }
}
