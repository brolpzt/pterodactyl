<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove billing, support tickets, and deploy plans tables/columns.
     */
    public function up(): void
    {
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_departments');

        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('wallets');

        Schema::dropIfExists('deploy_plan_variable_overrides');
        Schema::dropIfExists('deploy_plans');

        Schema::table('servers', function (Blueprint $table) {
            $columns = array_filter([
                'billing_type',
                'hourly_rate',
                'monthly_rate',
                'billing_cost_so_far',
                'next_due_date',
                'suspended_for_billing_at',
            ], fn (string $column) => Schema::hasColumn('servers', $column));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally not reversible — feature removal migration.
    }
};
