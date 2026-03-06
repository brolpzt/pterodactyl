<?php

namespace Pterodactyl\Http\Controllers\Api\Client;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Egg;
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
     * Get egg variables for a plan (loaded dynamically when plan is selected).
     */
    public function variables(string $planId): JsonResponse
    {
        $user = $this->request->user();
        if (!$user->root_admin) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $planConfig = config("deploy.plans.{$planId}");
        if (!$planConfig) {
            return new JsonResponse(['error' => 'Invalid plan'], 404);
        }

        $egg = Egg::with('variables')->find($planConfig['egg_id'] ?? 0);
        if (!$egg) {
            return new JsonResponse(['egg_variables' => []]);
        }

        $variables = $egg->variables
            ->where('user_viewable', true)
            ->values()
            ->map(fn ($v) => [
                'name' => $v->name,
                'description' => $v->description,
                'env_variable' => $v->env_variable,
                'default_value' => $v->default_value,
                'user_editable' => $v->user_editable,
                'rules' => explode('|', $v->rules),
            ])
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

        $planIds = implode(',', array_keys(config('deploy.plans', [])));
        $request->validate([
            'name' => 'sometimes|nullable|string|min:1|max:191',
            'plan_id' => 'required|string|in:' . $planIds,
            'location_ids' => 'required|array',
            'location_ids.*' => 'integer|exists:locations,id',
            'billing_type' => 'required|string|in:hourly,monthly,quarterly,semi_annually,annually',
            'environment' => 'sometimes|array',
            'environment.*' => 'nullable|string',
        ]);

        $planConfig = config("deploy.plans.{$request->input('plan_id')}");
        if (!$planConfig) {
            return new JsonResponse(['error' => 'Invalid plan'], 422);
        }

        $egg = Egg::with(['nest', 'variables'])->find($planConfig['egg_id']);
        if (!$egg) {
            return new JsonResponse(['error' => 'Plan configuration error: egg not found'], 500);
        }

        $environment = $this->buildEnvironment($egg, $request->input('environment', []));

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
            'memory' => $planConfig['memory'],
            'swap' => $planConfig['swap'] ?? 0,
            'disk' => $planConfig['disk'],
            'io' => $planConfig['io'] ?? 500,
            'cpu' => $planConfig['cpu'],
            'image' => $image,
            'startup' => $egg->startup ?? '',
            'database_limit' => 0,
            'allocation_limit' => 0,
            'backup_limit' => 0,
            'environment' => $environment,
            'billing_type' => $request->input('billing_type'),
            'hourly_rate' => $planConfig['hourly_rate'],
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
     * Build full environment array from user input and egg defaults.
     * User-editable vars use request values; others use default_value.
     */
    protected function buildEnvironment(Egg $egg, array $userInput): array
    {
        $environment = [];
        foreach ($egg->variables as $variable) {
            if ($variable->user_editable && $variable->user_viewable) {
                $value = $userInput[$variable->env_variable] ?? $variable->default_value;
                $environment[$variable->env_variable] = $value !== null ? (string) $value : $variable->default_value;
            } else {
                $environment[$variable->env_variable] = $variable->default_value ?? '';
            }
        }
        return $environment;
    }

    /**
     * Return eggs with their plan. User selects Egg first, then sees the plan for that egg.
     */
    protected function getAvailableEggs(): array
    {
        $eggs = [];
        foreach (config('deploy.plans', []) as $planId => $config) {
            $egg = Egg::find($config['egg_id'] ?? 0);
            if (!$egg) {
                continue;
            }
            $discount = config("billing.period_discounts.monthly", 0.75);
            $days = config("billing.period_days.monthly", 30);
            $monthlyPrice = round($config['hourly_rate'] * $days * 24 * $discount, 2);

            $eggs[] = [
                'egg_id' => $egg->id,
                'egg_name' => $egg->name,
                'plan_id' => $planId,
                'plan' => [
                    'name' => $config['name'],
                    'hourly_rate' => $config['hourly_rate'],
                    'monthly_price' => $monthlyPrice,
                    'memory' => $config['memory'],
                    'disk' => $config['disk'],
                    'cpu' => $config['cpu'],
                ],
            ];
        }
        return $eggs;
    }
}
