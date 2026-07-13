<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->boolean('warn_hostname_branding')->default(false)->after('warn_slot_mismatch');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('warn_hostname_branding')->nullable()->after('warn_slot_mismatch');
        });

        DB::table('eggs')
            ->where('name', 'like', 'Plutonium%')
            ->update([
                'warn_hostname_branding' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn('warn_hostname_branding');
        });

        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('warn_hostname_branding');
        });
    }
};
