<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\AddonCategory;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Admin\AddonCategoryFormRequest;

class AddonCategoriesController extends Controller
{
    /**
     * AddonCategoriesController constructor.
     */
    public function __construct(protected AlertsMessageBag $alert)
    {
    }

    /**
     * Render the addon categories index page.
     */
    public function index(): View
    {
        return view('admin.addon_categories.index', [
            'categories' => AddonCategory::with('egg.nest')->get(),
        ]);
    }

    /**
     * Render the addon category creation page.
     */
    public function create(): View
    {
        return view('admin.addon_categories.new', [
            'nests' => Nest::with('eggs')->get(),
        ]);
    }

    /**
     * Handle request to store a new addon category.
     */
    public function store(AddonCategoryFormRequest $request): RedirectResponse
    {
        $category = (new AddonCategory())->forceFill($request->validated());

        $category->save();

        $this->alert->success('Addon Category has been created successfully.')->flash();

        return redirect()->route('admin.addon-categories.view', $category->id);
    }

    /**
     * Render the addon category view page.
     */
    public function view(AddonCategory $category): View
    {
        return view('admin.addon_categories.view', [
            'category' => $category,
            'nests' => Nest::with('eggs')->get(),
        ]);
    }

    /**
     * Handle request to update an addon category.
     */
    public function update(AddonCategoryFormRequest $request, AddonCategory $category): RedirectResponse
    {
        $category->fill($request->validated())->save();

        $this->alert->success('Addon Category has been updated successfully.')->flash();

        return redirect()->route('admin.addon-categories.view', $category->id);
    }

    /**
     * Handle request to delete an addon category.
     */
    public function delete(AddonCategory $category): RedirectResponse
    {
        $category->delete();

        $this->alert->success('Addon Category has been deleted successfully.')->flash();

        return redirect()->route('admin.addon-categories');
    }
}
