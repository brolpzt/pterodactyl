<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Addon;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\AddonFormRequest;

class AddonsController extends Controller
{
    /**
     * AddonsController constructor.
     */
    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    /**
     * Render the addon index page.
     */
    public function index(): View
    {
        return view('admin.addons.index', [
            'addons' => Addon::with('egg.nest')->get(),
        ]);
    }

    /**
     * Render the addon creation page.
     */
    public function create(): View
    {
        return view('admin.addons.new', [
            'nests' => Nest::with('eggs.addonCategories')->get(),
        ]);
    }

    /**
     * Handle request to store a new addon.
     */
    public function store(AddonFormRequest $request): RedirectResponse
    {
        $addon = (new Addon())->forceFill([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        ]);

        $data = $request->validated();
        $data['is_active'] = $request->has('is_active');
        $data['reinstall_server'] = $request->has('reinstall_server');
        
        $addon->fill($data)->save();

        $this->alert->success('Addon has been created successfully.')->flash();

        return redirect()->route('admin.addons.view', $addon->id);
    }

    /**
     * Render the addon view page.
     */
    public function view(Addon $addon): View
    {
        return view('admin.addons.view', [
            'addon' => $addon,
            'nests' => Nest::with('eggs.addonCategories')->get(),
        ]);
    }

    /**
     * Handle request to update an addon.
     */
    public function update(AddonFormRequest $request, Addon $addon): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->has('is_active');
        $data['reinstall_server'] = $request->has('reinstall_server');
        
        $addon->fill($data)->save();

        $this->alert->success('Addon has been updated successfully.')->flash();

        return redirect()->route('admin.addons.view', $addon->id);
    }

    /**
     * Handle request to delete an addon.
     */
    public function delete(Addon $addon): RedirectResponse
    {
        $addon->delete();

        $this->alert->success('Addon has been deleted successfully.')->flash();

        return redirect()->route('admin.addons');
    }
}
