<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $categories = InventoryCategory::query()
            ->withCount('items')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (InventoryCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'items' => $c->items_count,
            ]);

        return Inertia::render('Inventory/Categories', [
            'categories' => $categories,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        InventoryCategory::create($request->validate(['name' => ['required', 'string', 'max:100']]));

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, InventoryCategory $category): RedirectResponse
    {
        $category->update($request->validate(['name' => ['required', 'string', 'max:100']]));

        return back()->with('success', 'Category updated.');
    }

    public function destroy(InventoryCategory $category): RedirectResponse
    {
        $category->delete();

        return back()->with('success', 'Category removed.');
    }
}
