<?php

namespace Pterodactyl\Services\Gcore;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\GcoreSyncRun;
use Pterodactyl\Models\EggDdosProfile;
use Pterodactyl\Models\EggDdosSetting;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\ServerDdosProfileState;
use Pterodactyl\Services\Eggs\PortSlotSyncService;

/**
 * Lets the client pick, among the options of the server's egg, the Gcore policy of the
 * game port. The choice is stored as the primary allocation's Gcore override (the same
 * one the profile editor's "pin" writes) and pushed to Gcore by the usual port sync.
 */
class DdosProfileService
{
    public const ACTOR_CLIENT = 'client';
    public const ACTOR_ADMIN = 'admin';

    public function __construct(private PortSlotSyncService $portSlotSync)
    {
    }

    public function settingsFor(Egg $egg): EggDdosSetting
    {
        return EggDdosSetting::query()->firstOrNew(['egg_id' => $egg->id]);
    }

    /**
     * @return Collection<int, EggDdosProfile>
     */
    public function profilesFor(Egg $egg): Collection
    {
        return EggDdosProfile::query()->where('egg_id', $egg->id)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Everything needed to show or change the server's profile.
     *
     * @return array{
     *     available: bool,
     *     reason: string|null,
     *     allocation: Allocation|null,
     *     default: array{policy: string, proto: string|null}|null,
     *     policy: string|null,
     *     settings: EggDdosSetting,
     *     profiles: Collection<int, EggDdosProfile>,
     *     state: ServerDdosProfileState,
     *     current: EggDdosProfile|null,
     *     custom: bool,
     * }
     */
    public function context(Server $server): array
    {
        $server->loadMissing(['egg', 'node.gcoreIps']);
        $egg = $server->egg;
        $settings = $egg ? $this->settingsFor($egg) : new EggDdosSetting();
        $profiles = $egg ? $this->profilesFor($egg) : collect();
        $state = ServerDdosProfileState::query()->firstOrNew(['server_id' => $server->id]);

        $context = [
            'available' => false,
            'reason' => null,
            'allocation' => null,
            'default' => null,
            'policy' => null,
            'settings' => $settings,
            'profiles' => $profiles,
            'state' => $state,
            'current' => null,
            'custom' => false,
        ];

        $allocation = $server->allocation_id ? Allocation::query()->find($server->allocation_id) : null;
        $node = $server->node;
        if (!$egg || !$allocation || !$node || !$node->gcore_enabled || !$allocation->gcore_protected || !$node->hasGcoreIp((string) $allocation->ip)) {
            $context['reason'] = 'A porta principal deste servidor não está protegida pela Gcore.';

            return $context;
        }

        $default = $this->portSlotSync->resolveEggGcoreForAllocation($egg, $allocation, (int) $allocation->id);
        if ($default === null) {
            $context['reason'] = 'O egg não define um perfil da Gcore para a porta principal.';

            return $context;
        }

        $override = $allocation->activeGcoreOverride();
        $policy = $override['policy'] ?? $default['policy'];

        $current = null;
        if ($override !== null && $state->egg_ddos_profile_id) {
            $current = $profiles->first(fn (EggDdosProfile $p) => $p->id === $state->egg_ddos_profile_id && $p->policy === $policy);
        }
        $current ??= $profiles->first(fn (EggDdosProfile $p) => $p->policy === $policy);

        return array_merge($context, [
            'available' => true,
            'allocation' => $allocation,
            'default' => $default,
            'policy' => $policy,
            'current' => $current,
            // A policy pinned by the support team outside the egg's options.
            'custom' => $override !== null && $current === null,
        ]);
    }

    /**
     * When the client may change the profile again (null: now).
     */
    public function nextClientChangeAt(array $context): ?CarbonImmutable
    {
        // A change that Gcore could not apply does not cost the client a cooldown.
        if (($this->syncStatus($context['state'])['status'] ?? null) === 'failed') {
            return null;
        }

        $changedAt = $context['state']->client_changed_at;
        $minutes = (int) $context['settings']->cooldown_minutes;
        if (!$changedAt || $minutes <= 0) {
            return null;
        }

        $next = CarbonImmutable::instance($changedAt)->addMinutes($minutes);

        return $next->isFuture() ? $next : null;
    }

    /**
     * Payload of the client's Anti-DDoS profile card. The Gcore policy names never leave the panel.
     */
    public function clientPayload(Server $server): array
    {
        $context = $this->context($server);
        $settings = $context['settings'];
        $enabled = $context['available'] && $settings->enabled && $context['profiles']->isNotEmpty();
        $next = $this->nextClientChangeAt($context);

        $current = $context['current'];

        return [
            'enabled' => $enabled,
            'locked' => (bool) $context['state']->client_locked,
            'custom' => $context['custom'],
            'cooldown_minutes' => (int) $settings->cooldown_minutes,
            'next_change_at' => $next?->toIso8601String(),
            'sync' => !$enabled ? null : $this->syncStatus($context['state'], forClient: true),
            'current' => !$enabled ? null : [
                'id' => $current?->id,
                'label' => $current?->label ?? ($context['custom'] ? 'Configuração personalizada pelo suporte' : 'Proteção padrão'),
                'alert' => $current?->alertPayload(),
            ],
            'options' => !$enabled ? [] : $context['profiles']->map(fn (EggDdosProfile $p) => [
                'id' => $p->id,
                'label' => $p->label,
                'alert' => $p->alertPayload(),
            ])->values()->all(),
        ];
    }

    /**
     * The client picks one of the egg's options, subject to the cooldown and the admin lock.
     *
     * @throws DisplayException
     */
    public function changeByClient(Server $server, int $profileId, ?User $user = null): void
    {
        DB::transaction(function () use ($server, $profileId, $user) {
            // Serialize concurrent requests for the same server: the cooldown check must see the last change.
            ServerDdosProfileState::query()->firstOrCreate(['server_id' => $server->id]);
            ServerDdosProfileState::query()->where('server_id', $server->id)->lockForUpdate()->first();

            $context = $this->context($server);
            if (!$context['available'] || !$context['settings']->enabled || $context['profiles']->isEmpty()) {
                throw new DisplayException('A mudança do perfil de proteção não está disponível para este servidor.');
            }
            if ($context['state']->client_locked) {
                throw new DisplayException('A mudança do perfil de proteção foi bloqueada pelo suporte para este servidor.');
            }
            if ($context['custom']) {
                throw new DisplayException('Este servidor usa uma configuração personalizada pelo suporte. Abra um ticket para alterá-la.');
            }

            /** @var EggDdosProfile|null $profile */
            $profile = $context['profiles']->firstWhere('id', $profileId);
            if ($profile === null) {
                throw new DisplayException('Perfil de proteção inválido.');
            }
            if ($context['current']?->id === $profile->id) {
                throw new DisplayException('Este perfil já está ativo.');
            }

            $next = $this->nextClientChangeAt($context);
            if ($next !== null) {
                $minutes = max(1, (int) ceil(now()->diffInSeconds($next, true) / 60));
                throw new DisplayException("Você poderá mudar o perfil de proteção novamente em {$minutes} min.");
            }

            $this->apply($server, $context, $profile->policy, $profile, self::ACTOR_CLIENT, $user);
        });
    }

    /**
     * Admin change: any egg option, any Gcore policy, or back to the egg default. No cooldown.
     *
     * @param int|null $profileId egg option, or null with $policy (null: egg default)
     *
     * @throws DisplayException
     */
    public function changeByAdmin(Server $server, ?int $profileId, ?string $policy, ?User $user = null): void
    {
        DB::transaction(function () use ($server, $profileId, $policy, $user) {
            $context = $this->context($server);
            if (!$context['available']) {
                throw new DisplayException($context['reason'] ?? 'Indisponível para este servidor.');
            }

            $profile = null;
            if ($profileId !== null) {
                $profile = $context['profiles']->firstWhere('id', $profileId);
                if ($profile === null) {
                    throw new DisplayException('Esta opção não pertence ao egg do servidor.');
                }
                $policy = $profile->policy;
            } elseif ($policy !== null && !in_array($policy, GcoreClient::POLICIES, true)) {
                throw new DisplayException('Perfil da Gcore inválido.');
            }

            $this->apply($server, $context, $policy ?? $context['default']['policy'], $profile, self::ACTOR_ADMIN, $user);
        });
    }

    public function setClientLocked(Server $server, bool $locked): void
    {
        $state = ServerDdosProfileState::query()->firstOrNew(['server_id' => $server->id]);
        $state->client_locked = $locked;
        $state->save();
    }

    public function resetCooldown(Server $server): void
    {
        ServerDdosProfileState::query()->where('server_id', $server->id)->update(['client_changed_at' => null]);
    }

    /**
     * Servers of the egg that picked an option through this feature go back to the egg
     * default (feature disabled or options removed). Overrides pinned by hand are kept.
     *
     * @param list<int>|null $profileIds only servers on these options (null: all)
     *
     * @return int servers reverted
     */
    public function revertEggServers(Egg $egg, string $reason, ?array $profileIds = null): int
    {
        $states = ServerDdosProfileState::query()
            ->whereNotNull('egg_ddos_profile_id')
            ->whereHas('server', fn ($q) => $q->where('egg_id', $egg->id))
            ->when($profileIds !== null, fn ($q) => $q->whereIn('egg_ddos_profile_id', $profileIds))
            ->with(['profile', 'server'])
            ->get();

        $count = 0;
        foreach ($states as $state) {
            $server = $state->server;
            $allocation = $server?->allocation_id ? Allocation::query()->find($server->allocation_id) : null;
            $override = $allocation?->activeGcoreOverride();
            if ($allocation && $override !== null && $state->profile && $override['policy'] === $state->profile->policy) {
                $this->writeOverride($allocation, null);
                $state->gcore_sync_run_id = $this->queueSync($server, $allocation, $reason)?->id;
                ++$count;
            }
            $state->forceFill(['egg_ddos_profile_id' => null])->save();
        }

        return $count;
    }

    /**
     * Gcore policy the egg gives the game port (SERVER_PORT slot), for the admin page.
     */
    public function eggDefaultPolicy(Egg $egg): ?string
    {
        $slots = $this->portSlotSync->withLegacyPrimarySlot(is_array($egg->port_slots) ? $egg->port_slots : [], $egg);
        foreach ($slots as $slot) {
            if ($slot['env_variable'] === PortSlotSyncService::PRIMARY_ENV) {
                $policy = trim((string) ($slot['gcore_policy'] ?? ''));

                return $policy === '' ? null : $policy;
            }
        }

        return null;
    }

    /**
     * An option now points at another Gcore policy: servers using it follow.
     *
     * @return int servers updated
     */
    public function repointProfile(EggDdosProfile $profile, string $oldPolicy, string $reason): int
    {
        $states = ServerDdosProfileState::query()->where('egg_ddos_profile_id', $profile->id)->with('server')->get();

        $count = 0;
        foreach ($states as $state) {
            $server = $state->server;
            $allocation = $server?->allocation_id ? Allocation::query()->find($server->allocation_id) : null;
            $override = $allocation?->activeGcoreOverride();
            if (!$allocation) {
                continue;
            }
            $context = $this->context($server);
            if (!$context['available']) {
                continue;
            }
            // Only servers still on the option's old policy (or the egg default it stood for).
            $onOption = $override !== null ? $override['policy'] === $oldPolicy : $context['default']['policy'] === $oldPolicy;
            if (!$onOption) {
                continue;
            }
            $this->writeOverride($allocation, $profile->policy === $context['default']['policy'] ? null : ['policy' => $profile->policy, 'proto' => $context['default']['proto']]);
            $state->forceFill(['gcore_sync_run_id' => $this->queueSync($server, $allocation, $reason)?->id])->save();
            ++$count;
        }

        return $count;
    }

    private function apply(Server $server, array $context, string $policy, ?EggDdosProfile $profile, string $actor, ?User $user): void
    {
        /** @var Allocation $allocation */
        $allocation = $context['allocation'];
        $default = $context['default'];
        // The activity log is visible to the client: never show Gcore policy names there.
        $previous = $context['current']?->label ?? ($context['custom'] ? 'Configuração personalizada' : 'Proteção padrão');

        // The egg default needs no override, so later egg changes still reach the server.
        $this->writeOverride($allocation, $policy === $default['policy'] ? null : ['policy' => $policy, 'proto' => $default['proto']]);

        $who = $actor === self::ACTOR_CLIENT ? 'cliente' : 'admin';
        $run = $this->queueSync($server, $allocation, "perfil anti-DDoS alterado pelo {$who}: {$server->name} (#{$server->id}) → {$policy}");

        $state = ServerDdosProfileState::query()->firstOrNew(['server_id' => $server->id]);
        $state->egg_ddos_profile_id = $profile?->id;
        $state->gcore_sync_run_id = $run?->id;
        if ($actor === self::ACTOR_CLIENT) {
            $state->client_changed_at = now();
        }
        $state->save();

        $label = $profile?->label ?? ($policy === $default['policy'] ? 'Proteção padrão' : 'Configuração personalizada');

        $event = Activity::event('server:antiddos.profile')
            ->subject($server)
            ->property(['old' => $previous, 'new' => $label]);
        if ($user !== null) {
            $event->actor($user);
        }
        $event->log();
    }

    /**
     * @param array{policy: string, proto: string|null}|null $override
     */
    private function writeOverride(Allocation $allocation, ?array $override): void
    {
        $allocation->forceFill([
            'gcore_override_policy' => $override['policy'] ?? null,
            'gcore_override_proto' => $override === null ? null : $override['proto'],
            'gcore_override_server_id' => $override === null ? null : $allocation->server_id,
        ])->save();
    }

    private function queueSync(Server $server, Allocation $allocation, string $reason): ?GcoreSyncRun
    {
        $server->loadMissing('node');

        return $server->node ? app(GcoreSyncQueue::class)->ports($server->node, (string) $allocation->ip, $reason) : null;
    }

    /**
     * Where the Gcore sync of the last change stands: pending (queued or running), applied or failed.
     * A failed run counts as applied once a later sync of the same IP succeeded.
     *
     * @return array{status: string, finished_at: string|null, run_id?: int, summary?: string|null}|null
     */
    public function syncStatus(ServerDdosProfileState $state, bool $forClient = false): ?array
    {
        $run = $state->gcore_sync_run_id ? GcoreSyncRun::query()->find($state->gcore_sync_run_id) : null;
        if ($run === null) {
            return null;
        }

        $status = match (true) {
            $run->isPending() => 'pending',
            $run->status === 'ok' => 'applied',
            default => 'failed',
        };
        $finishedAt = $run->finished_at;
        if ($status === 'failed') {
            $later = GcoreSyncRun::query()
                ->where('kind', GcoreSyncRun::KIND_PORTS)
                ->where('ip', $run->ip)
                ->where('id', '>', $run->id)
                ->where('status', 'ok')
                ->orderBy('id')
                ->first();
            if ($later !== null) {
                [$status, $finishedAt] = ['applied', $later->finished_at];
            }
        }

        $payload = ['status' => $status, 'finished_at' => $finishedAt?->toIso8601String()];
        if (!$forClient) {
            // Admin only: the run's summary names Gcore policies.
            $payload += ['run_id' => $run->id, 'summary' => $run->summary];
        }

        return $payload;
    }
}
