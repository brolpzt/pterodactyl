<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\DeployPlan;
use Pterodactyl\Models\DeployPlanVariableOverride;
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
        $data['monthly_rate'] = isset($data['monthly_rate']) && $data['monthly_rate'] !== '' ? $data['monthly_rate'] : null;

        DeployPlan::create($data);
        $this->alert->success('Deploy plan was created successfully.')->flash();

        return redirect()->route('admin.deploy_plans');
    }

    public function view(DeployPlan $plan): View
    {
        $plan->load(['egg.nest', 'egg.variables', 'variableOverrides.eggVariable']);
        $eggs = Egg::with('nest')->orderBy('nest_id')->orderBy('name')->get();

        return view('admin.deploy_plans.view', ['plan' => $plan, 'eggs' => $eggs]);
    }

    public function update(DeployPlanFormRequest $request, DeployPlan $plan): RedirectResponse
    {
        $data = $request->normalize();
        $variableOverrides = $data['variable_overrides'] ?? [];
        unset($data['variable_overrides']);

        $data['swap'] = $data['swap'] ?? 0;
        $data['io'] = $data['io'] ?? 500;
        $data['monthly_rate'] = isset($data['monthly_rate']) && $data['monthly_rate'] !== '' ? $data['monthly_rate'] : null;

        $eggChanged = isset($data['egg_id']) && (int) $data['egg_id'] !== (int) $plan->egg_id;
        if ($eggChanged) {
            $plan->variableOverrides()->delete();
        }

        $plan->update($data);

        $plan->load('egg.variables');
        $validVariableIds = $plan->egg->variables->pluck('id')->toArray();

        foreach ($variableOverrides as $eggVariableId => $value) {
            $eggVariableId = (int) $eggVariableId;
            if (!in_array($eggVariableId, $validVariableIds, true)) {
                continue;
            }
            DeployPlanVariableOverride::updateOrCreate(
                [
                    'deploy_plan_id' => $plan->id,
                    'egg_variable_id' => $eggVariableId,
                ],
                ['value' => $value !== null && $value !== '' ? (string) $value : null]
            );
        }

        $plan->variableOverrides()->whereNotIn('egg_variable_id', array_keys($variableOverrides))->delete();

        $this->alert->success('Deploy plan was updated successfully.')->flash();

        return redirect()->route('admin.deploy_plans.view', $plan->id);
    }

    public function clone(DeployPlan $plan): RedirectResponse
    {
        $plan->load(['egg.variables', 'variableOverrides']);
        $newPlan = $plan->replicate();
        $newPlan->name = $plan->name . ' (copy)';
        $newPlan->save();

        foreach ($plan->variableOverrides as $override) {
            $newPlan->variableOverrides()->create([
                'egg_variable_id' => $override->egg_variable_id,
                'value' => $override->value,
            ]);
        }

        $this->alert->success('Deploy plan cloned. You can edit the new plan below.')->flash();

        return redirect()->route('admin.deploy_plans.view', $newPlan->id);
    }

    public function destroy(DeployPlan $plan): RedirectResponse
    {
        $plan->delete();
        $this->alert->success('Deploy plan was deleted.')->flash();

        return redirect()->route('admin.deploy_plans');
    }
}
