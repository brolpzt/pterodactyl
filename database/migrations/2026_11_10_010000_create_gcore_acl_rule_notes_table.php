<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gcore ACL rules have no comment field: descriptions live in the panel and are matched
        // back to rules by policy+proto+sport+source (dports only break ties, since the port
        // sync rewrites them on managed rules).
        Schema::create('gcore_acl_rule_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->index();
            $table->string('rule_key', 191);
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('dport_list');
            $table->string('description', 160);
            $table->timestamps();

            $table->index(['profile_id', 'rule_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcore_acl_rule_notes');
    }
};
