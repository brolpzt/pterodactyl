<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gcore_ip_list_bindings', function (Blueprint $table) {
            // Last read-back of the rule from Gcore: when, how many list CIDRs were checked / missing.
            $table->timestamp('verified_at')->nullable()->after('applied_at');
            $table->unsignedInteger('verify_checked')->nullable()->after('verified_at');
            // null = the read-back itself failed (see verify_message)
            $table->unsignedInteger('verify_missing')->nullable()->after('verify_checked');
            $table->string('verify_message', 500)->nullable()->after('verify_missing');
        });
    }

    public function down(): void
    {
        Schema::table('gcore_ip_list_bindings', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'verify_checked', 'verify_missing', 'verify_message']);
        });
    }
};
