<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\EggForcedCommand;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\ServerForcedCommandOverride;
use Pterodactyl\Services\Servers\ForcedConsoleCommandService;

class ServerForcedCommandController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private ForcedConsoleCommandService $forcedCommandService,
    ) {
    }

    public function index(Server $server): View
    {
        $server->load(['egg.forcedCommands', 'forcedCommandOverrides', 'egg.variables']);

        $rows = $server->egg->forcedCommands->map(function (EggForcedCommand $forced) use ($server) {
            $effective = $this->forcedCommandService->resolveEffective($server, $forced);

            return [
                'forced' => $forced,
                'override' => $effective['override'],
                'effective' => $effective,
            ];
        });

        return view('admin.servers.view.forced-commands', [
            'server' => $server,
            'rows' => $rows,
        ]);
    }

    public function update(Request $request, Server $server, EggForcedCommand $forcedCommand): RedirectResponse
    {
        abort_unless((int) $forcedCommand->egg_id === (int) $server->egg_id, 404);

        $enabledRaw = $request->input('enabled');
        $hideRaw = $request->input('hide_output');
        $intervalRaw = $request->input('interval_seconds');

        $enabled = $enabledRaw === '' || $enabledRaw === null
            ? null
            : filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $hideOutput = $hideRaw === '' || $hideRaw === null
            ? null
            : filter_var($hideRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $interval = $intervalRaw === '' || $intervalRaw === null
            ? null
            : (int) $intervalRaw;

        if (!is_null($interval)) {
            $request->validate([
                'interval_seconds' => 'integer|min:15|max:3600',
            ]);
        }

        ServerForcedCommandOverride::query()->updateOrCreate(
            [
                'server_id' => $server->id,
                'egg_forced_command_id' => $forcedCommand->id,
            ],
            [
                'enabled' => $enabled,
                'hide_output' => $hideOutput,
                'interval_seconds' => $interval,
            ]
        );

        $this->alert->success('Override do comando forçado guardado.')->flash();

        return redirect()->route('admin.servers.view.forced-commands', $server->id);
    }

    public function forceNow(Server $server, EggForcedCommand $forcedCommand): RedirectResponse
    {
        abort_unless((int) $forcedCommand->egg_id === (int) $server->egg_id, 404);

        $sent = $this->forcedCommandService->forceNow($server, $forcedCommand);

        if ($sent) {
            $this->alert->success('Comando forçado enviado agora.')->flash();
        } else {
            $this->alert->warning('Comando não enviado (servidor offline, desativado ou ENV vazio).')->flash();
        }

        return redirect()->route('admin.servers.view.forced-commands', $server->id);
    }
}
