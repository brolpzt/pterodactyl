<?php

namespace Pterodactyl\Http\Controllers\Api\Application;

use Pterodactyl\Models\Addon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Pterodactyl\Transformers\Api\Application\AddonTransformer;

class AddonsController extends ApplicationApiController
{
    /**
     * Handle request to list all addons on the panel.
     */
    public function index(Request $request): array
    {
        $addons = QueryBuilder::for(Addon::query())
            ->allowedFilters(['egg_id', 'category_id', 'name', 'uuid'])
            ->paginate($request->query('per_page') ?? 50);

        return $this->fractal->collection($addons)
            ->transformWith($this->getTransformer(AddonTransformer::class))
            ->toArray();
    }

    /**
     * Handle a request to view a single addon.
     */
    public function view(Request $request, Addon $addon): array
    {
        return $this->fractal->item($addon)
            ->transformWith($this->getTransformer(AddonTransformer::class))
            ->toArray();
    }

    /**
     * Store a new addon.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, Addon::$validationRules);
        
        $addon = (new Addon())->forceFill([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
        ]);
        
        $addon->fill($data)->save();

        return $this->fractal->item($addon)
            ->transformWith($this->getTransformer(AddonTransformer::class))
            ->respond(201);
    }

    /**
     * Update an existing addon.
     */
    public function update(Request $request, Addon $addon): array
    {
        $data = $this->validate($request, Addon::$validationRules);
        
        $addon->fill($data)->save();

        return $this->fractal->item($addon)
            ->transformWith($this->getTransformer(AddonTransformer::class))
            ->toArray();
    }

    /**
     * Handle a request to delete an addon.
     */
    public function delete(Request $request, Addon $addon): JsonResponse
    {
        $addon->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
