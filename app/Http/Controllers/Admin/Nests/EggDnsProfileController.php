<?php

namespace Pterodactyl\Http\Controllers\Admin\Nests;

use Pterodactyl\Models\Egg;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Cloudflare\EggDnsProfileService;
use Pterodactyl\Http\Requests\Admin\Egg\EggDnsProfileFormRequest;

class EggDnsProfileController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert,
        protected EggDnsProfileService $profileService,
    ) {
    }

    public function update(EggDnsProfileFormRequest $request, Egg $egg): RedirectResponse
    {
        $this->profileService->syncForEgg($egg, $request->validated());
        $this->alert->success('Configuração DNS do egg atualizada com sucesso.')->flash();

        return redirect()->route('admin.nests.egg.view', $egg->id);
    }
}
