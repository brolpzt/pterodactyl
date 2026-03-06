<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfills billing_cost_so_far for servers that had it reset (e.g. after column rollback).
     * Sums: initial charge (1x hourly_rate) + all server charges from wallet_transactions.
     */
    public function up(): void
    {
        $servers = DB::table('servers')
            ->whereNotNull('billing_type')
            ->whereNotNull('hourly_rate')
            ->where('hourly_rate', '>', 0)
            ->get(['id', 'owner_id', 'hourly_rate', 'billing_type', 'billing_cost_so_far']);

        foreach ($servers as $server) {
            $serverCharges = DB::table('wallet_transactions')
                ->where('type', 'charge')
                ->where('reference_type', 'server')
                ->where('reference_id', $server->id)
                ->sum(DB::raw('ABS(amount)'));

            $initialCharge = 0.0;
            if ($server->billing_type === 'hourly') {
                $initialCharge = (float) $server->hourly_rate;
            } elseif (in_array($server->billing_type, ['monthly', 'quarterly', 'semi_annually', 'annually'], true)) {
                $discount = config("billing.period_discounts.{$server->billing_type}", 1);
                $days = config("billing.period_days.{$server->billing_type}", 30);
                $initialCharge = round((float) $server->hourly_rate * $days * 24 * $discount, 2);
            }

            $total = round($initialCharge + (float) $serverCharges, 2);

            DB::table('servers')
                ->where('id', $server->id)
                ->update(['billing_cost_so_far' => $total]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: backfill is not reversible
    }
};
