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
        Schema::table('servers', function (Blueprint $table) {
            $table->string('billing_type', 20)->nullable()->after('status')->comment('hourly, monthly, quarterly, semi_annually, annually');
            $table->timestamp('next_due_date')->nullable()->after('billing_type');
            $table->decimal('hourly_rate', 12, 4)->nullable()->after('next_due_date')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['billing_type', 'next_due_date', 'hourly_rate']);
        });
    }
};
