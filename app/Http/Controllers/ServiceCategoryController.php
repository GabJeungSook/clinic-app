<?php

namespace App\Http\Controllers;

use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $categories = ServiceCategory::query()
            ->withCount('services')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ServiceCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sort_order' => $c->sort_order,
                'services' => $c->services_count,
            ]);

        return Inertia::render('Services/Categories', [
            'categories' => $categories,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ServiceCategory::create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]));

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $serviceCategory->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]));

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        $serviceCategory->delete();

        return back()->with('success', 'Category removed.');
    }
}
