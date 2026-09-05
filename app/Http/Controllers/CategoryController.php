<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
        ]);

        Category::create(['name' => trim($validated['name']), 'is_active' => true]);

        return redirect()->back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name,' . $category->id,
        ]);

        $category->update(['name' => trim($validated['name'])]);

        return redirect()->back()->with('success', 'Category updated.');
    }

    public function deactivate(Category $category)
    {
        $category->update(['is_active' => false]);

        return redirect()->back()->with('success', 'Category deactivated.');
    }

    public function activate(Category $category)
    {
        $category->update(['is_active' => true]);

        return redirect()->back()->with('success', 'Category activated.');
    }
}
