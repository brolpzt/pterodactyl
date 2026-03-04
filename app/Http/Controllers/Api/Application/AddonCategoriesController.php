<?php

namespace Pterodactyl\Http\Controllers\Api\Application;

use Pterodactyl\Models\AddonCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Pterodactyl\Transformers\Api\Application\AddonCategoryTransformer;

class AddonCategoriesController extends ApplicationApiController
{
    /**
     * Handle request to list all addon categories.
     */
    public function index(Request $request): array
    {
        $categories = QueryBuilder::for(AddonCategory::query())
            ->allowedFilters(['egg_id', 'name'])
            ->paginate($request->query('per_page') ?? 50);

        return $this->fractal->collection($categories)
            ->transformWith($this->getTransformer(AddonCategoryTransformer::class))
            ->toArray();
    }

    /**
     * Handle a request to view a single addon category.
     */
    public function view(Request $request, AddonCategory $category): array
    {
        return $this->fractal->item($category)
            ->transformWith($this->getTransformer(AddonCategoryTransformer::class))
            ->toArray();
    }

    /**
     * Store a new addon category.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, AddonCategory::$validationRules);
        
        $category = (new AddonCategory())->forceFill($data);
        $category->save();

        return $this->fractal->item($category)
            ->transformWith($this->getTransformer(AddonCategoryTransformer::class))
            ->respond(201);
    }

    /**
     * Update an existing addon category.
     */
    public function update(Request $request, AddonCategory $category): array
    {
        $data = $this->validate($request, AddonCategory::$validationRules);
        
        $category->fill($data)->save();

        return $this->fractal->item($category)
            ->transformWith($this->getTransformer(AddonCategoryTransformer::class))
            ->toArray();
    }

    /**
     * Handle a request to delete an addon category.
     */
    public function delete(Request $request, AddonCategory $category): JsonResponse
    {
        $category->delete();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }
}
