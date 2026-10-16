<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\EggForcedCommand;
use Pterodactyl\Http\Controllers\Controller;

class EggForcedCommandController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(Egg $egg): View
    {
        $egg->load(['variables', 'forcedCommands', 'nest']);

        return view('admin.eggs.forced-commands', [
            'egg' => $egg,
            'forcedCommands' => $egg->forcedCommands()->orderBy('env_variable')->get(),
        ]);
    }

    public function store(Request $request, Egg $egg): RedirectResponse
    {
        $data = $this->validated($request);
        $data['egg_id'] = $egg->id;
        $data['enabled'] = $request->boolean('enabled');
        $data['hide_output'] = $request->boolean('hide_output');

        EggForcedCommand::query()->create($data);

        $this->alert->success('Comando forçado criado com sucesso.')->flash();

        return redirect()->route('admin.nests.egg.forced-commands', $egg->id);
    }

    public function update(Request $request, Egg $egg, EggForcedCommand $forcedCommand): RedirectResponse
    {
        $this->assertBelongsToEgg($egg, $forcedCommand);

        $data = $this->validated($request);
        $data['enabled'] = $request->boolean('enabled');
        $data['hide_output'] = $request->boolean('hide_output');

        $forcedCommand->update($data);

        $this->alert->success('Comando forçado atualizado.')->flash();

        return redirect()->route('admin.nests.egg.forced-commands', $egg->id);
    }

    public function destroy(Egg $egg, EggForcedCommand $forcedCommand): RedirectResponse
    {
        $this->assertBelongsToEgg($egg, $forcedCommand);
        $forcedCommand->delete();

        $this->alert->success('Comando forçado removido.')->flash();

        return redirect()->route('admin.nests.egg.forced-commands', $egg->id);
    }

    /**
     * @return array{env_variable: string, command_template: string, interval_seconds: int}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'env_variable' => 'required|string|regex:/^[\w]{1,191}$/',
            'command_template' => 'required|string|max:191',
            'interval_seconds' => 'required|integer|min:15|max:3600',
        ]);
    }

    private function assertBelongsToEgg(Egg $egg, EggForcedCommand $forcedCommand): void
    {
        abort_unless((int) $forcedCommand->egg_id === (int) $egg->id, 404);
    }
}
