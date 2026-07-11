<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Models\CloudflareZone;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\CloudflareDnsRecord;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Cloudflare\CloudflareAccountService;
use Pterodactyl\Http\Requests\Admin\Cloudflare\CloudflareAccountFormRequest;
use Pterodactyl\Http\Requests\Admin\Cloudflare\CloudflareZoneFormRequest;

class CloudflareController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert,
        protected CloudflareAccountService $accountService,
    ) {
    }

    public function index(): View
    {
        $account = \Pterodactyl\Models\CloudflareAccount::query()->first();
        $zones = CloudflareZone::query()->withCount('dnsRecords')->orderBy('domain')->get();
        $records = CloudflareDnsRecord::query()
            ->with(['server', 'zone'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.cloudflare.index', [
            'account' => $account,
            'zones' => $zones,
            'records' => $records,
        ]);
    }

    public function updateAccount(CloudflareAccountFormRequest $request): RedirectResponse
    {
        try {
            $this->accountService->saveAccount($request->validated());
            $this->alert->success('Configuração Cloudflare salva com sucesso.')->flash();
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.cloudflare');
    }

    public function createZone(): View
    {
        return view('admin.cloudflare.zones.new');
    }

    public function storeZone(CloudflareZoneFormRequest $request): RedirectResponse
    {
        try {
            $account = \Pterodactyl\Models\CloudflareAccount::query()->first();
            if (!$account) {
                throw new DisplayException('Configure o token da API Cloudflare antes de adicionar domínios.');
            }

            $this->accountService->createZone($account, $request->validated());
            $this->alert->success('Domínio adicionado com sucesso.')->flash();
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.cloudflare.zones.new');
        }

        return redirect()->route('admin.cloudflare');
    }

    public function viewZone(CloudflareZone $zone): View
    {
        return view('admin.cloudflare.zones.view', [
            'zone' => $zone,
        ]);
    }

    public function updateZone(CloudflareZoneFormRequest $request, CloudflareZone $zone): RedirectResponse
    {
        try {
            $this->accountService->updateZone($zone, $request->validated());
            $this->alert->success('Domínio atualizado com sucesso.')->flash();
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();
        }

        return redirect()->route('admin.cloudflare.zones.view', $zone->id);
    }

    public function deleteZone(CloudflareZone $zone): RedirectResponse
    {
        try {
            $this->accountService->deleteZone($zone);
            $this->alert->success('Domínio removido com sucesso.')->flash();
        } catch (DisplayException $exception) {
            $this->alert->danger($exception->getMessage())->flash();

            return redirect()->route('admin.cloudflare.zones.view', $zone->id);
        }

        return redirect()->route('admin.cloudflare');
    }
}
