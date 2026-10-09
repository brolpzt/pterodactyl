<?php

namespace Pterodactyl\Services\Eggs;

use Illuminate\Support\Arr;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Services\Gcore\GcoreClient;
use Pterodactyl\Exceptions\DisplayException;
use Illuminate\Database\ConnectionInterface;

class PortSlotSyncService
{
    public const PRIMARY_ENV = 'SERVER_PORT';

    public function __construct(private ConnectionInterface $connection)
    {
    }

    /**
     * Normalize and validate raw port slot definitions from the admin form / import.
     *
     * SERVER_PORT is allowed as the special primary slot (ACL only; not an egg variable).
     *
     * @param  mixed  $raw
     * @return list<array{env_variable: string, name: string, description: string, required: bool, gcore_policy: string|null, gcore_proto: string|null}>
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

            $gcorePolicy = trim((string) Arr::get($row, 'gcore_policy', ''));
            $gcoreProto = strtolower(trim((string) Arr::get($row, 'gcore_proto', '')));

            if ($env === '' && $name === '' && $gcorePolicy === '') {
                continue;
            }

            if ($env === '' || !preg_match('/^[\w]{1,191}$/', $env)) {
                throw new DisplayException("port_slots[{$index}]: env_variable inválido.");
            }

            $isPrimary = $env === self::PRIMARY_ENV;
            if (!$isPrimary && in_array($env, $reserved, true)) {
                throw new DisplayException("port_slots[{$index}]: {$env} é um nome reservado.");
            }

            if (isset($seen[$env])) {
                throw new DisplayException("port_slots: env_variable duplicado ({$env}).");
            }
            $seen[$env] = true;

            if ($gcorePolicy !== '' && !in_array($gcorePolicy, GcoreClient::POLICIES, true)) {
                throw new DisplayException("port_slots[{$index}]: gcore_policy inválida ({$gcorePolicy}).");
            }

            if ($gcoreProto !== '' && !in_array($gcoreProto, GcoreClient::PROTOCOLS, true)) {
                throw new DisplayException("port_slots[{$index}]: gcore_proto inválido ({$gcoreProto}).");
            }

            if ($name === '') {
                $name = $isPrimary ? 'Game Port' : $env;
            }

            // Primary is always the allocation default — never "optional auto-assign".
            if ($isPrimary) {
                $required = true;
            }

            $out[] = [
                'env_variable' => $env,
                'name' => $name,
                'description' => $description,
                'required' => $required,
                'gcore_policy' => $gcorePolicy === '' ? null : $gcorePolicy,
                'gcore_proto' => $gcoreProto === '' || $gcoreProto === 'any' ? null : $gcoreProto,
            ];
        }

        return $out;
    }

    /**
     * Merge legacy egg.gcore_policy / gcore_proto into a SERVER_PORT slot when missing.
     *
     * @param  list<array<string, mixed>>  $slots
     * @return list<array{env_variable: string, name: string, description: string, required: bool, gcore_policy: string|null, gcore_proto: string|null}>
     */
    public function withLegacyPrimarySlot(array $slots, ?Egg $egg): array
    {
        $slots = $this->normalizeSlots($slots);
        $hasPrimary = false;
        foreach ($slots as $slot) {
            if ($slot['env_variable'] === self::PRIMARY_ENV) {
                $hasPrimary = true;
                break;
            }
        }

        if ($hasPrimary || !$egg) {
            return $slots;
        }

        $policy = trim((string) ($egg->gcore_policy ?? ''));
        $proto = strtolower(trim((string) ($egg->gcore_proto ?? '')));
        if ($policy === '' && $proto === '') {
            return $slots;
        }

        array_unshift($slots, [
            'env_variable' => self::PRIMARY_ENV,
            'name' => 'Game Port',
            'description' => 'Allocation primária (SERVER_PORT).',
            'required' => true,
            'gcore_policy' => $policy !== '' && in_array($policy, GcoreClient::POLICIES, true) ? $policy : null,
            'gcore_proto' => $proto !== '' && $proto !== 'any' && in_array($proto, GcoreClient::PROTOCOLS, true) ? $proto : null,
        ]);

        return $slots;
    }

    /**
     * Slots shown in admin UI (always include SERVER_PORT row).
     *
     * @return list<array<string, mixed>>
     */
    public function slotsForEdit(Egg $egg): array
    {
        $slots = $this->withLegacyPrimarySlot(
            is_array($egg->port_slots) ? $egg->port_slots : [],
            $egg
        );

        if ($slots === []) {
            return [[
                'env_variable' => self::PRIMARY_ENV,
                'name' => 'Game Port',
                'description' => 'Allocation primária (SERVER_PORT).',
                'required' => true,
                'gcore_policy' => null,
                'gcore_proto' => null,
            ]];
        }

        return $slots;
    }

    /**
     * Extra (non-primary) port slots.
     *
     * @param  list<array<string, mixed>>|null  $slots
     * @return list<array{env_variable: string, name: string, description: string, required: bool, gcore_policy: string|null, gcore_proto: string|null}>
     */
    public function extraSlots(?array $slots): array
    {
        return array_values(array_filter(
            $this->normalizeSlots($slots ?? []),
            fn (array $s) => $s['env_variable'] !== self::PRIMARY_ENV
        ));
    }

    /**
     * Resolve Gcore ACL policy/proto for an allocation via its port slot.
     *
     * @return array{policy: string, proto: string|null}|null
     */
    public function resolveGcoreForAllocation(Egg $egg, Allocation $allocation, int $primaryAllocationId): ?array
    {
        $slots = $this->withLegacyPrimarySlot(
            is_array($egg->port_slots) ? $egg->port_slots : [],
            $egg
        );
        $byEnv = [];
        foreach ($slots as $slot) {
            $byEnv[$slot['env_variable']] = $slot;
        }

        $isPrimary = (int) $allocation->id === (int) $primaryAllocationId;
        $env = $isPrimary
            ? self::PRIMARY_ENV
            : strtoupper(trim((string) ($allocation->port_env ?? '')));

        if ($env === '') {
            return null;
        }

        $slot = $byEnv[$env] ?? null;
        if ($slot === null) {
            return null;
        }

        $policy = trim((string) ($slot['gcore_policy'] ?? ''));
        if ($policy === '' || !in_array($policy, GcoreClient::POLICIES, true)) {
            return null;
        }

        $proto = strtolower(trim((string) ($slot['gcore_proto'] ?? '')));
        if ($proto === '' || $proto === 'any' || !in_array($proto, GcoreClient::PROTOCOLS, true)) {
            $proto = null;
        }

        return ['policy' => $policy, 'proto' => $proto];
    }

    /**
     * Mirror extra port_slots into egg_variables (non-editable). SERVER_PORT is never mirrored.
     */
    public function syncEggVariables(Egg $egg, ?array $previousSlots = null): void
    {
        $slots = $this->extraSlots($egg->port_slots ?? []);
        $currentEnvs = array_column($slots, 'env_variable');
        $previousEnvs = array_column($this->extraSlots($previousSlots ?? []), 'env_variable');

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
     * Auto-assign free allocations on the same IP for required extra slots still unbound.
     *
     * @throws DisplayException
     */
    public function assignRequiredSlots(Server $server): void
    {
        $server->loadMissing(['egg', 'allocation', 'allocations', 'node']);

        $slots = $this->extraSlots($server->egg?->port_slots ?? []);
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

        if ($portEnv === self::PRIMARY_ENV) {
            throw new DisplayException('SERVER_PORT é reservado à allocation primária.');
        }

        if ($portEnv !== null) {
            $slots = $this->extraSlots($server->egg?->port_slots ?? []);
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
     * Write allocation ports into server_variables for each bound extra port slot.
     */
    public function syncServerVariables(Server $server): void
    {
        $server->loadMissing(['allocations', 'egg.variables']);

        $slots = $this->extraSlots($server->egg?->port_slots ?? []);
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
     * @return list<array{env_variable: string, name: string, description: string, required: bool, gcore_policy: string|null, gcore_proto: string|null}>
     */
    public function unfilledSlots(Server $server): array
    {
        $server->loadMissing(['egg', 'allocations']);
        $slots = $this->extraSlots($server->egg?->port_slots ?? []);
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
     * Raise allocation_limit so extra slots can fit with primary.
     */
    public function ensureAllocationLimit(Server $server, ?int $extraSlotCount = null): void
    {
        $extraSlotCount ??= count($this->extraSlots($server->egg?->port_slots ?? []));
        $minimum = 1 + max(0, $extraSlotCount);
        $current = (int) ($server->allocation_limit ?? 0);

        if ($current < $minimum) {
            $server->forceFill(['allocation_limit' => $minimum])->save();
        }
    }

    /**
     * Whether the egg defines a port base (start). End is optional.
     */
    public function hasPortRange(?Egg $egg): bool
    {
        if (!$egg) {
            return false;
        }

        $start = (int) ($egg->port_range_start ?? 0);

        return $start >= 1 && $start <= 65535;
    }

    public function portStep(?Egg $egg): int
    {
        $step = (int) ($egg?->port_step ?? 1);

        return max(1, min(100, $step));
    }

    /**
     * Optional upper bound; null means open-ended (only start + step matter).
     */
    public function portRangeEnd(?Egg $egg): ?int
    {
        if (!$egg) {
            return null;
        }

        $start = (int) ($egg->port_range_start ?? 0);
        $end = (int) ($egg->port_range_end ?? 0);
        if ($end < 1) {
            return null;
        }
        if ($start >= 1 && $end < $start) {
            return null;
        }

        return min(65535, $end);
    }

    /**
     * Primary pool as an explicit list when end is set.
     * When end is empty, returns null — use constrainQueryToPrimaryPool instead.
     *
     * @return list<int>|null
     */
    public function primaryPoolPorts(?Egg $egg): ?array
    {
        if (!$this->hasPortRange($egg)) {
            return null;
        }

        $end = $this->portRangeEnd($egg);
        if ($end === null) {
            // Open-ended: do not expand to 65k ports.
            return null;
        }

        $start = (int) $egg->port_range_start;
        $step = $this->portStep($egg);
        $ports = [];
        for ($port = $start; $port <= $end; $port += $step) {
            $ports[] = $port;
        }

        return $ports;
    }

    /**
     * Extra slot candidates relative to primary: >= start, optional <= end,
     * (port - primary) % step == 0, port != primary.
     */
    public function isValidExtraPort(?Egg $egg, int $primaryPort, int $port): bool
    {
        if ($port === $primaryPort || $port < 1 || $port > 65535) {
            return false;
        }

        if ($this->hasPortRange($egg)) {
            $start = (int) $egg->port_range_start;
            if ($port < $start) {
                return false;
            }
            $end = $this->portRangeEnd($egg);
            if ($end !== null && $port > $end) {
                return false;
            }
        }

        $step = $this->portStep($egg);
        if ($step <= 1) {
            return true;
        }

        return (($port - $primaryPort) % $step) === 0;
    }

    public function isValidPrimaryPort(?Egg $egg, int $port): bool
    {
        if ($port < 1 || $port > 65535) {
            return false;
        }

        if (!$this->hasPortRange($egg)) {
            return true;
        }

        $start = (int) $egg->port_range_start;
        if ($port < $start) {
            return false;
        }

        $end = $this->portRangeEnd($egg);
        if ($end !== null && $port > $end) {
            return false;
        }

        $step = $this->portStep($egg);
        if ($step <= 1) {
            return true;
        }

        return (($port - $start) % $step) === 0;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Pterodactyl\Models\Allocation>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\Pterodactyl\Models\Allocation>
     */
    public function constrainQueryToExtraSlot($query, ?Egg $egg, int $primaryPort)
    {
        if ($this->hasPortRange($egg)) {
            $query->where('port', '>=', (int) $egg->port_range_start);
            $end = $this->portRangeEnd($egg);
            if ($end !== null) {
                $query->where('port', '<=', $end);
            }
        }

        $query->where('port', '!=', $primaryPort);

        $step = $this->portStep($egg);
        if ($step > 1) {
            $query->whereRaw('MOD(port - ?, ?) = 0', [$primaryPort, $step]);
        }

        return $query;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Pterodactyl\Models\Allocation>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\Pterodactyl\Models\Allocation>
     */
    public function constrainQueryToPrimaryPool($query, ?Egg $egg)
    {
        if (!$this->hasPortRange($egg)) {
            return $query;
        }

        $start = (int) $egg->port_range_start;
        $query->where('port', '>=', $start);

        $end = $this->portRangeEnd($egg);
        if ($end !== null) {
            $query->where('port', '<=', $end);
        }

        $step = $this->portStep($egg);
        if ($step > 1) {
            $query->whereRaw('MOD(port - ?, ?) = 0', [$start, $step]);
        }

        return $query;
    }

    /**
     * Upper bound used when creating new allocation rows (open end → 65535).
     */
    public function effectiveRangeEnd(?Egg $egg): int
    {
        return $this->portRangeEnd($egg) ?? 65535;
    }

    /**
     * @throws DisplayException
     */
    private function claimFreeAllocation(Server $server, string $ip): Allocation
    {
        $server->loadMissing(['egg', 'allocation']);
        $primaryPort = (int) ($server->allocation?->port ?? 0);

        $query = Allocation::query()
            ->where('node_id', $server->node_id)
            ->where('ip', $ip)
            ->whereNull('server_id')
            ->orderBy('port');

        if ($primaryPort > 0) {
            $this->constrainQueryToExtraSlot($query, $server->egg, $primaryPort);
        }

        /** @var Allocation|null $allocation */
        $allocation = $query->lockForUpdate()->first();

        if (!$allocation) {
            $hint = '';
            if ($this->hasPortRange($server->egg)) {
                $end = $this->portRangeEnd($server->egg);
                $hint = sprintf(
                    ' Range do egg: >= %d%s step %d (a partir da primary %d).',
                    (int) $server->egg->port_range_start,
                    $end !== null ? "–{$end}" : '',
                    $this->portStep($server->egg),
                    $primaryPort
                );
            }

            throw new DisplayException(
                "Sem allocation livre no IP {$ip} para o port slot obrigatório.{$hint}"
            );
        }

        return $allocation;
    }
}
