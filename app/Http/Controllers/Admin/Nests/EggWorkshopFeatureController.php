<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Pterodactyl\Models\Egg;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Steam\EggWorkshopFeatureService;
use Pterodactyl\Http\Requests\Admin\Egg\EggWorkshopFeatureFormRequest;

class EggWorkshopFeatureController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert,
        protected EggWorkshopFeatureService $workshopFeatureService,
    ) {
    }

    public function update(EggWorkshopFeatureFormRequest $request, Egg $egg): RedirectResponse
    {
        $this->workshopFeatureService->syncForEgg($egg, $request->validated()['enabled']);
        $this->alert->success('Configuração Steam Workshop do egg atualizada com sucesso.')->flash();

        return redirect()->route('admin.nests.egg.view', $egg->id);
    }
}
