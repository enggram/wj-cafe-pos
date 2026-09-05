<?php

namespace App\Http\Controllers;

use App\Contracts\MenuServiceInterface;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function __construct(
        private readonly MenuServiceInterface $menuService
    ) {}

    /**
     * List all menu items grouped by category.
     */
    public function index(): Response
    {
        $categories = $this->menuService->listByCategory();
        $allCategories = Category::orderBy('name')->get();

        return Inertia::render('Menu/Index', [
            'categories'    => $categories,
            'allCategories' => $allCategories,
        ]);
    }

    /**
     * Create a new menu item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0.01|max:99999.99',
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        $this->menuService->createItem($validated);

        return redirect()->back()->with('success', 'Menu item created successfully.');
    }

    /**
     * Update an existing menu item.
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0.01|max:99999.99',
            'category_id' => 'required|integer|exists:categories,id',
        ]);

        $this->menuService->updateItem($id, $validated);

        return redirect()->back()->with('success', 'Menu item updated successfully.');
    }

    /**
     * Deactivate a menu item.
     */
    public function deactivate(int $id)
    {
        $this->menuService->deactivateItem($id);

        return redirect()->back()->with('success', 'Menu item deactivated successfully.');
    }

    /**
     * Create a sub-variety for a menu item.
     */
    public function storeSubVariety(Request $request, int $menuItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'price_adjustment' => 'nullable|numeric|min:-99999.99|max:99999.99',
        ]);

        $this->menuService->createSubVariety($menuItem, $validated);

        return redirect()->back()->with('success', 'Sub-variety created successfully.');
    }
}
