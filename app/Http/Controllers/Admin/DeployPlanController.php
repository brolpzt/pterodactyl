<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\DeployPlan;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\DeployPlanFormRequest;
class DeployPlanController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert,
    ) {
    }

    public function index(): View
    {
        $plans = DeployPlan::with('egg.nest')->orderBy('egg_id')->orderBy('name')->get();

        return view('admin.deploy_plans.index', ['plans' => $plans]);
    }

    public function create(): View
    {
        $eggs = Egg::with('nest')->orderBy('nest_id')->orderBy('name')->get();

        return view('admin.deploy_plans.new', ['eggs' => $eggs]);
    }

    public function store(DeployPlanFormRequest $request): RedirectResponse
    {
        $data = $request->normalize();
        $data['swap'] = $data['swap'] ?? 0;
        $data['io'] = $data['io'] ?? 500;

        DeployPlan::create($data);
        $this->alert->success('Deploy plan was created successfully.')->flash();

        return redirect()->route('admin.deploy_plans');
    }

    public function view(DeployPlan $plan): View
    {
        $plan->load('egg.nest');
        $eggs = Egg::with('nest')->orderBy('nest_id')->orderBy('name')->get();

        return view('admin.deploy_plans.view', ['plan' => $plan, 'eggs' => $eggs]);
    }

    public function update(DeployPlanFormRequest $request, DeployPlan $plan): RedirectResponse
    {
        if ($request->input('action') === 'delete') {
            return $this->delete($plan);
        }

        $data = $request->normalize();
        $data['swap'] = $data['swap'] ?? 0;
        $data['io'] = $data['io'] ?? 500;

        $plan->update($data);
        $this->alert->success('Deploy plan was updated successfully.')->flash();

        return redirect()->route('admin.deploy_plans.view', $plan->id);
    }

    public function delete(DeployPlan $plan): RedirectResponse
    {
        $plan->delete();
        $this->alert->success('Deploy plan was deleted.')->flash();

        return redirect()->route('admin.deploy_plans');
    }
}
