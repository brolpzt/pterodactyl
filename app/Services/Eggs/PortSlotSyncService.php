<?php

namespace Pterodactyl\Services\Eggs;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Database\ConnectionInterface;

class PortSlotSyncService
{
    public function __construct(private ConnectionInterface $connection)
    {
    }

    /**
     * Normalize and validate raw port slot definitions from the admin form / import.
     *
     * @param  mixed  $raw
     * @return list<array{env_variable: string, name: string, description: string, required: bool}>
     *
     * @throws DisplayException
     */
    public function normalizeSlots(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new DisplayException('port_slots inválido: JSON malformado.');
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            throw new DisplayException('port_slots inválido.');
        }

        $reserved = array_map('strtoupper', explode(',', EggVariable::RESERVED_ENV_NAMES));
        $seen = [];
        $out = [];

        foreach (array_values($raw) as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $env = strtoupper(trim((string) Arr::get($row, 'env_variable', '')));
            $name = trim((string) Arr::get($row, 'name', ''));
            $description = trim((string) Arr::get($row, 'description', ''));
            $requiredRaw = Arr::get($row, 'required', false);
            if (is_array($requiredRaw)) {
                $requiredRaw = end($requiredRaw);
            }
            $required = filter_var($requiredRaw, FILTER_VALIDATE_BOOLEAN);

            if ($env === '' && $name === '') {
                continue;
            }

            if ($env === '' || !preg_match('/^[\w]{1,191}$/', $env)) {
                throw new DisplayException("port_slots[{$index}]: env_variable inválido.");
            }

            if (in_array($env, $reserved, true)) {
                throw new DisplayException("port_slots[{$index}]: {$env} é um nome reservado.");
            }

            if (isset($seen[$env])) {
                throw new DisplayException("port_slots: env_variable duplicado ({$env}).");
            }
            $seen[$env] = true;

            if ($name === '') {
                $name = $env;
            }

            $out[] = [
                'env_variable' => $env,
                'name' => $name,
                'description' => $description,
                'required' => $required,
            ];
        }

        return $out;
    }

    /**
     * Mirror port_slots into egg_variables (non-editable) so {{ENV}} works in startup.
     */
    public function syncEggVariables(Egg $egg, ?array $previousSlots = null): void
    {
        $slots = $this->normalizeSlots($egg->port_slots ?? []);
        $currentEnvs = array_column($slots, 'env_variable');
        $previousEnvs = array_column($this->normalizeSlots($previousSlots ?? []), 'env_variable');

        $this->connection->transaction(function () use ($egg, $slots, $currentEnvs, $previousEnvs) {
            foreach ($slots as $slot) {
                EggVariable::query()->updateOrCreate(
                    [
                        'egg_id' => $egg->id,
                        'env_variable' => $slot['env_variable'],
                    ],
                    [
                        'name' => $slot['name'],
                        'description' => $slot['description'] !== ''
                            ? $slot['description']
                            : 'Porta gerenciada pela allocation (port slot).',
                        'default_value' => '',
                        'user_viewable' => true,
                        'user_editable' => false,
                        'rules' => 'nullable|integer|between:1,65535',
                    ]
                );
            }

            $toRemove = array_values(array_diff($previousEnvs, $currentEnvs));
            if ($toRemove !== []) {
                EggVariable::query()
                    ->where('egg_id', $egg->id)
                    ->whereIn('env_variable', $toRemove)
                    ->where('user_editable', false)
                    ->where('rules', 'nullable|integer|between:1,65535')
                    ->delete();
            }
        });
    }

    /**
     * Auto-assign free allocations on the same IP for required slots still unbound.
     *
     * @throws DisplayException
     */
    public function assignRequiredSlots(Server $server): void
    {
        $server->loadMissing(['egg', 'allocation', 'allocations', 'node']);

        $slots = $this->normalizeSlots($server->egg?->port_slots ?? []);
        $required = array_values(array_filter($slots, fn (array $s) => $s['required']));
        if ($required === []) {
            return;
        }

        $bound = $server->allocations
            ->filter(fn (Allocation $a) => $a->port_env !== null && $a->port_env !== '')
            ->pluck('port_env')
            ->map(fn ($v) => strtoupper((string) $v))
            ->all();

        $primary = $server->allocation;
        if (!$primary) {
            throw new DisplayException('Servidor sem allocation primária para atribuir port slots.');
        }

        $needed = array_values(array_filter(
            $required,
            fn (array $s) => !in_array($s['env_variable'], $bound, true)
        ));

        if ($needed === []) {
            $this->syncServerVariables($server);

            return;
        }

        $this->ensureAllocationLimit($server, count($slots));

        foreach ($needed as $slot) {
            $allocation = $this->claimFreeAllocation($server, $primary->ip);
            $allocation->forceFill([
                'server_id' => $server->id,
                'port_env' => $slot['env_variable'],
            ])->save();
        }

        $server->unsetRelation('allocations');
        $this->syncServerVariables($server->fresh(['allocations', 'egg.variables']));
    }

    /**
     * Bind (or clear) a port slot on an allocation already belonging to the server.
     *
     * @throws DisplayException
     */
    public function bindAllocation(Server $server, Allocation $allocation, ?string $portEnv): void
    {
        if ((int) $allocation->server_id !== (int) $server->id) {
            throw new DisplayException('Allocation não pertence a este servidor.');
        }

        if ((int) $allocation->id === (int) $server->allocation_id) {
            if ($portEnv !== null && $portEnv !== '') {
                throw new DisplayException('A allocation primária é sempre SERVER_PORT e não pode ter port slot.');
            }
            $allocation->forceFill(['port_env' => null])->save();
            $this->syncServerVariables($server);

            return;
        }

        $portEnv = $portEnv !== null ? strtoupper(trim($portEnv)) : null;
        if ($portEnv === '') {
            $portEnv = null;
        }

        if ($portEnv !== null) {
            $slots = $this->normalizeSlots($server->egg?->port_slots ?? []);
            $valid = array_column($slots, 'env_variable');
            if (!in_array($portEnv, $valid, true)) {
                throw new DisplayException("Port slot {$portEnv} não existe neste egg.");
            }

            $taken = Allocation::query()
                ->where('server_id', $server->id)
                ->where('port_env', $portEnv)
                ->where('id', '!=', $allocation->id)
                ->exists();
            if ($taken) {
                throw new DisplayException("O slot {$portEnv} já está atribuído a outra porta.");
            }
        }

        $allocation->forceFill(['port_env' => $portEnv])->save();
        $this->syncServerVariables($server->fresh(['allocations', 'egg.variables']));
    }

    /**
     * Clear port_env when allocations are released and refresh server variables.
     *
     * @param  list<int>  $allocationIds
     */
    public function clearPortEnvForAllocations(array $allocationIds): void
    {
        if ($allocationIds === []) {
            return;
        }

        Allocation::query()
            ->whereIn('id', $allocationIds)
            ->update(['port_env' => null]);
    }

    /**
     * Write allocation ports into server_variables for each bound port slot.
     */
    public function syncServerVariables(Server $server): void
    {
        $server->loadMissing(['allocations', 'egg.variables']);

        $slots = $this->normalizeSlots($server->egg?->port_slots ?? []);
        if ($slots === []) {
            return;
        }

        $portByEnv = [];
        foreach ($server->allocations as $allocation) {
            $env = strtoupper(trim((string) ($allocation->port_env ?? '')));
            if ($env !== '' && (int) $allocation->id !== (int) $server->allocation_id) {
                $portByEnv[$env] = (string) $allocation->port;
            }
        }

        foreach ($slots as $slot) {
            $variable = $server->egg->variables->firstWhere('env_variable', $slot['env_variable']);
            if (!$variable) {
                continue;
            }

            $value = $portByEnv[$slot['env_variable']] ?? '';

            ServerVariable::query()->updateOrCreate(
                [
                    'server_id' => $server->id,
                    'variable_id' => $variable->id,
                ],
                [
                    'variable_value' => $value,
                ]
            );
        }
    }

    /**
     * @return list<array{env_variable: string, name: string, description: string, required: bool}>
     */
    public function unfilledSlots(Server $server): array
    {
        $server->loadMissing(['egg', 'allocations']);
        $slots = $this->normalizeSlots($server->egg?->port_slots ?? []);
        $bound = $server->allocations
            ->pluck('port_env')
            ->filter()
            ->map(fn ($v) => strtoupper((string) $v))
            ->all();

        return array_values(array_filter(
            $slots,
            fn (array $s) => !in_array($s['env_variable'], $bound, true)
        ));
    }

    /**
     * Raise allocation_limit so required (+ optional room) can fit with primary.
     */
    public function ensureAllocationLimit(Server $server, ?int $slotCount = null): void
    {
        $slotCount ??= count($this->normalizeSlots($server->egg?->port_slots ?? []));
        $minimum = 1 + max(0, $slotCount);
        $current = (int) ($server->allocation_limit ?? 0);

        if ($current < $minimum) {
            $server->forceFill(['allocation_limit' => $minimum])->save();
        }
    }

    /**
     * @throws DisplayException
     */
    private function claimFreeAllocation(Server $server, string $ip): Allocation
    {
        /** @var Allocation|null $allocation */
        $allocation = Allocation::query()
            ->where('node_id', $server->node_id)
            ->where('ip', $ip)
            ->whereNull('server_id')
            ->orderBy('port')
            ->lockForUpdate()
            ->first();

        if (!$allocation) {
            throw new DisplayException(
                "Sem allocation livre no IP {$ip} para o port slot obrigatório. Crie portas extras no node."
            );
        }

        return $allocation;
    }
}
