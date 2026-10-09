<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

/**
 * Fold legacy eggs.gcore_policy / gcore_proto into a SERVER_PORT entry in port_slots.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('eggs', 'port_slots')) {
            return;
        }

        $eggs = DB::table('eggs')->select(['id', 'port_slots', 'gcore_policy', 'gcore_proto'])->get();

        foreach ($eggs as $egg) {
            $policy = trim((string) ($egg->gcore_policy ?? ''));
            $proto = strtolower(trim((string) ($egg->gcore_proto ?? '')));
            if ($policy === '' && $proto === '') {
                continue;
            }

            $slots = [];
            if (!empty($egg->port_slots)) {
                $decoded = is_string($egg->port_slots)
                    ? json_decode($egg->port_slots, true)
                    : $egg->port_slots;
                if (is_array($decoded)) {
                    $slots = $decoded;
                }
            }

            $hasPrimary = false;
            foreach ($slots as $slot) {
                if (!is_array($slot)) {
                    continue;
                }
                if (strtoupper((string) ($slot['env_variable'] ?? '')) === 'SERVER_PORT') {
                    $hasPrimary = true;
                    break;
                }
            }

            if (!$hasPrimary) {
                array_unshift($slots, [
                    'env_variable' => 'SERVER_PORT',
                    'name' => 'Game Port',
                    'description' => 'Allocation primária (SERVER_PORT).',
                    'required' => true,
                    'gcore_policy' => $policy !== '' ? $policy : null,
                    'gcore_proto' => ($proto !== '' && $proto !== 'any') ? $proto : null,
                ]);
            }

            DB::table('eggs')->where('id', $egg->id)->update([
                'port_slots' => json_encode(array_values($slots)),
                'gcore_policy' => null,
                'gcore_proto' => null,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible data fold — no-op.
    }
};
