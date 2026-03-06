<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\DeployPlan;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Objects\DeploymentObject;
use Pterodactyl\Services\Billing\WalletService;
use Pterodactyl\Services\Servers\ServerCreationService;
use Illuminate\Http\Request;

class DeployController extends ClientApiController
{
    public function __construct(
        private WalletService $walletService,
        private ServerCreationService $serverCreationService,
    ) {
        parent::__construct();
    }

    /**
     * Get deploy options: plans, locations, wallet balance.
     */
    public function index(): array
    {
        $user = $this->request->user();

        if (!$user->root_admin) {
            return ['eggs' => [], 'locations' => [], 'wallet_balance' => 0];
        }

        $wallet = $this->walletService->getOrCreateWallet($user);
        $locations = Location::with('nodes')->get()->map(fn ($loc) => [
            'id' => $loc->id,
            'short' => $loc->short,
            'long' => $loc->long,
        ]);

        $eggs = $this->getAvailableEggs();

        return [
            'eggs' => $eggs,
            'locations' => $locations,
            'wallet_balance' => (float) $wallet->balance,
        ];
    }

    /**
     * Get egg variables for a plan (loaded dynamically when egg/plan is selected).
     * Plan variable overrides are used as default_value when set.
     */
    public function variables(int $planId): JsonResponse
    {
        $user = $this->request->user();
        if (!$user->root_admin) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $plan = DeployPlan::with(['egg.variables', 'variableOverrides'])->find($planId);
        if (!$plan || !$plan->egg) {
            return new JsonResponse(['egg_variables' => []]);
        }

        $overrideByVariableId = $plan->variableOverrides->keyBy('egg_variable_id');

        $egg = $plan->egg;
        $variables = $egg->variables
            ->where('user_viewable', true)
            ->values()
            ->map(function ($v) use ($overrideByVariableId) {
                $override = $overrideByVariableId->get($v->id);
                $defaultValue = $override !== null && $override->value !== null && $override->value !== ''
                    ? $override->value
                    : $v->default_value;

                return [
                    'name' => $v->name,
                    'description' => $v->description,
                    'env_variable' => $v->env_variable,
                    'default_value' => $defaultValue,
                    'user_editable' => $v->user_editable,
                    'rules' => explode('|', $v->rules),
                ];
            })
            ->toArray();

        return new JsonResponse(['egg_variables' => $variables]);
    }

    /**
     * Create a new server via deploy flow.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->root_admin) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'sometimes|nullable|string|min:1|max:191',
            'plan_id' => 'required|integer|exists:deploy_plans,id',
            'location_ids' => 'required|array',
            'location_ids.*' => 'integer|exists:locations,id',
            'billing_type' => 'required|string|in:hourly,monthly,quarterly,semi_annually,annually',
            'environment' => 'sometimes|array',
            'environment.*' => 'nullable|string',
        ]);

        $plan = DeployPlan::with(['egg.nest', 'egg.variables', 'variableOverrides.eggVariable'])->find($request->input('plan_id'));
        if (!$plan || !$plan->egg) {
            return new JsonResponse(['error' => 'Invalid plan'], 422);
        }

        $egg = $plan->egg;
        $planOverrides = $plan->variableOverrides
            ->filter(fn ($o) => $o->value !== null && $o->value !== '' && $o->eggVariable)
            ->mapWithKeys(fn ($o) => [$o->eggVariable->env_variable => $o->value])
            ->toArray();

        $environment = $this->buildEnvironment($egg, $request->input('environment', []), $planOverrides);

        $dockerImages = $egg->docker_images ?? [];
        $image = is_array($dockerImages) ? (array_values($dockerImages)[0] ?? $egg->image ?? 'ghcr.io/pterodactyl/yolks:java_17') : $egg->image;

        $deployment = new DeploymentObject();
        $deployment->setLocations($request->input('location_ids'));
        $deployment->setDedicated(false);
        $deployment->setPorts([]);

        $serverName = $request->input('name') ?: 'New Server';
        $data = [
            'name' => $serverName,
            'owner_id' => $user->id,
            'egg_id' => $egg->id,
            'nest_id' => $egg->nest_id,
            'memory' => $plan->memory,
            'swap' => $plan->swap ?? 0,
            'disk' => $plan->disk,
            'io' => $plan->io ?? 500,
            'cpu' => $plan->cpu,
            'image' => $image,
            'startup' => $egg->startup ?? '',
            'database_limit' => 0,
            'allocation_limit' => 0,
            'backup_limit' => 0,
            'environment' => $environment,
            'billing_type' => $request->input('billing_type'),
            'hourly_rate' => $plan->hourly_rate,
            'start_on_completion' => false,
        ];

        try {
            $server = $this->serverCreationService->handle($data, $deployment);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], 422);
        }

        return new JsonResponse([
            'success' => true,
            'server' => [
                'id' => $server->id,
                'uuid' => $server->uuid,
                'name' => $server->name,
            ],
        ], 201);
    }

    /**
     * Build full environment array from user input, plan overrides, and egg defaults.
     * Precedence: userInput > planOverrides > variable.default_value
     */
    protected function buildEnvironment(Egg $egg, array $userInput, array $planOverrides = []): array
    {
        $environment = [];
        foreach ($egg->variables as $variable) {
            if ($variable->user_editable && $variable->user_viewable) {
                $value = $userInput[$variable->env_variable]
                    ?? $planOverrides[$variable->env_variable]
                    ?? $variable->default_value;
                $environment[$variable->env_variable] = $value !== null ? (string) $value : ($variable->default_value ?? '');
            } else {
                $value = $planOverrides[$variable->env_variable] ?? $variable->default_value;
                $environment[$variable->env_variable] = $value !== null ? (string) $value : '';
            }
        }
        return $environment;
    }

    /**
     * Return eggs from database that have deploy plans. Each egg includes its plans.
     */
    protected function getAvailableEggs(): array
    {
        $days = config("billing.period_days.monthly", 30);

        $eggs = Egg::whereHas('deployPlans')
            ->with(['deployPlans', 'nest'])
            ->orderBy('nest_id')
            ->orderBy('name')
            ->get()
            ->map(function (Egg $egg) use ($days) {
                $plans = $egg->deployPlans->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'description' => $p->description,
                    'memory' => $p->memory,
                    'disk' => $p->disk,
                    'cpu' => $p->cpu,
                    'hourly_rate' => (float) $p->hourly_rate,
                    'monthly_price' => round($p->hourly_rate * $days * 24, 2),
                ])->values()->toArray();

                return [
                    'egg_id' => $egg->id,
                    'egg_name' => $egg->name,
                    'egg_description' => $egg->description,
                    'nest_name' => $egg->nest->name ?? null,
                    'plans' => $plans,
                ];
            })
            ->toArray();

        return $eggs;
    }
}
