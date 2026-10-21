<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('sftp_public_host', 191)->nullable()->after('daemonSFTP');
            $table->unsignedSmallInteger('sftp_public_port')->nullable()->after('sftp_public_host');
            $table->string('sftp_firewall_provider', 32)->default('none')->after('sftp_public_port');
            $table->text('sftp_firewall_api_key')->nullable()->after('sftp_firewall_provider');
            $table->string('sftp_firewall_group_id', 191)->nullable()->after('sftp_firewall_api_key');
            $table->unsignedSmallInteger('sftp_firewall_port')->nullable()->after('sftp_firewall_group_id');
            $table->unsignedInteger('sftp_firewall_ttl_minutes')->default(60)->after('sftp_firewall_port');
        });

        Schema::create('sftp_firewall_grants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('node_id');
            $table->unsignedInteger('server_id')->nullable();
            $table->string('client_ip', 45);
            $table->string('provider', 32);
            $table->string('external_rule_id', 191)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'node_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sftp_firewall_grants');

        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn([
                'sftp_public_host',
                'sftp_public_port',
                'sftp_firewall_provider',
                'sftp_firewall_api_key',
                'sftp_firewall_group_id',
                'sftp_firewall_port',
                'sftp_firewall_ttl_minutes',
            ]);
        });
    }
};
