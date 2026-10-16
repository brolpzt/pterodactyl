<?php

namespace Pterodactyl\Services\Servers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\EggForcedCommand;
use Pterodactyl\Models\ServerForcedCommandOverride;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Repositories\Wings\DaemonCommandRepository;

class ForcedConsoleCommandService
{
    public function __construct(
        private EnvironmentService $environmentService,
        private DaemonCommandRepository $commandRepository,
    ) {
    }

    /**
     * Process all active servers that have forced console commands due to run.
     *
     * @return array{processed: int, sent: int, skipped: int, failed: int}
     */
    public function processDue(): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        $servers = Server::query()
            ->whereNull('status')
            ->whereHas('egg.forcedCommands', fn ($q) => $q->where('enabled', true))
            ->with([
                'egg.forcedCommands' => fn ($q) => $q->where('enabled', true),
                'forcedCommandOverrides',
                'node',
                'variables',
            ])
            ->get();

        foreach ($servers as $server) {
            foreach ($server->egg->forcedCommands as $forced) {
                ++$stats['processed'];

                try {
                    $result = $this->processServerCommand($server, $forced);
                    if ($result === 'sent') {
                        ++$stats['sent'];
                    } else {
                        ++$stats['skipped'];
                    }
                } catch (\Throwable $exception) {
                    ++$stats['failed'];
                    Log::warning('Forced console command failed', [
                        'server_id' => $server->id,
                        'forced_command_id' => $forced->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return $stats;
    }

    /**
     * Force a single command for a server now (ignores interval).
     */
    public function forceNow(Server $server, EggForcedCommand $forced): bool
    {
        return $this->processServerCommand($server, $forced, true) === 'sent';
    }

    /**
     * Resolve effective settings for a forced command on a given server.
     *
     * @return array{enabled: bool, hide_output: bool, interval_seconds: int, override: ServerForcedCommandOverride|null}
     */
    public function resolveEffective(Server $server, EggForcedCommand $forced): array
    {
        /** @var ServerForcedCommandOverride|null $override */
        $override = $server->forcedCommandOverrides
            ->firstWhere('egg_forced_command_id', $forced->id);

        // Read raw attributes so null means "inherit from egg" (boolean cast would turn null into false).
        $overrideEnabled = $override?->getAttributes()['enabled'] ?? null;
        $overrideHide = $override?->getAttributes()['hide_output'] ?? null;
        $overrideInterval = $override?->getAttributes()['interval_seconds'] ?? null;

        $enabled = is_null($overrideEnabled) ? $forced->enabled : (bool) $overrideEnabled;
        $hideOutput = is_null($overrideHide) ? $forced->hide_output : (bool) $overrideHide;
        $interval = is_null($overrideInterval) ? $forced->interval_seconds : (int) $overrideInterval;

        return [
            'enabled' => (bool) $enabled,
            'hide_output' => (bool) $hideOutput,
            'interval_seconds' => max(15, (int) $interval),
            'override' => $override,
        ];
    }

    /**
     * @return 'sent'|'skipped'
     */
    private function processServerCommand(Server $server, EggForcedCommand $forced, bool $force = false): string
    {
        $effective = $this->resolveEffective($server, $forced);

        if (!$effective['enabled']) {
            return 'skipped';
        }

        $override = $effective['override'] ?? $this->ensureOverrideRow($server, $forced);

        if (!$force && !$this->isDue($override, $effective['interval_seconds'])) {
            return 'skipped';
        }

        $env = $this->environmentService->handle($server);
        $value = trim((string) ($env[$forced->env_variable] ?? ''));

        if ($value === '') {
            return 'skipped';
        }

        $command = $forced->buildCommand($value);
        if ($command === '') {
            return 'skipped';
        }

        try {
            // Always use stdin so clients cannot bypass enforcement by disabling RCON.
            $this->commandRepository
                ->setServer($server)
                ->send($command, $effective['hide_output'], 'stdin');
        } catch (DaemonConnectionException) {
            // Server offline / unreachable — skip silently; will retry next cycle.
            return 'skipped';
        }

        $override->forceFill(['last_forced_at' => CarbonImmutable::now()])->save();

        return 'sent';
    }

    private function isDue(ServerForcedCommandOverride $override, int $intervalSeconds): bool
    {
        if (is_null($override->last_forced_at)) {
            return true;
        }

        return $override->last_forced_at->lte(
            CarbonImmutable::now()->subSeconds($intervalSeconds)
        );
    }

    private function ensureOverrideRow(Server $server, EggForcedCommand $forced): ServerForcedCommandOverride
    {
        $override = ServerForcedCommandOverride::query()->firstOrCreate(
            [
                'server_id' => $server->id,
                'egg_forced_command_id' => $forced->id,
            ],
            [
                'enabled' => null,
                'hide_output' => null,
                'interval_seconds' => null,
            ]
        );

        $server->setRelation(
            'forcedCommandOverrides',
            $server->forcedCommandOverrides->push($override)->unique('id')
        );

        return $override;
    }
}
