<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequest;
use App\Models\InventoryItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $services = Service::query()
            ->with('category:id,name')
            ->withCount('consumables')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'category' => $s->category?->name,
                'sessions' => $s->default_session_count,
                'price' => (float) $s->default_price,
                'consumables' => $s->consumables_count,
                'is_active' => (bool) $s->is_active,
            ]);

        return Inertia::render('Services/Index', [
            'services' => $services,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Services/Create', [
            'categories' => $this->categories(),
            'items' => $this->items(),
            'units' => $this->units(),
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = Service::create($request->safe()->except('consumables'));

        // Optional bill of materials supplied on the create form.
        foreach ($request->validated('consumables') ?? [] as $c) {
            $service->consumables()->create($c);
        }

        return redirect()->route('services.edit', $service)->with('success', 'Service created.');
    }

    public function edit(Service $service): Response
    {
        $service->load('consumables.item:id,name', 'consumables.unit:id,abbreviation');

        return Inertia::render('Services/Edit', [
            'service' => $service->only([
                'id', 'name', 'code', 'service_category_id', 'description',
                'default_session_count', 'default_price', 'cost', 'default_interval_days',
                'duration_minutes', 'is_active',
            ]),
            'categories' => $this->categories(),
            'consumables' => $service->consumables->map(fn ($c) => [
                'id' => $c->id,
                'item' => $c->item?->name,
                'quantity' => (float) $c->quantity,
                'unit' => $c->unit?->abbreviation,
                'is_optional' => (bool) $c->is_optional,
            ]),
            'items' => $this->items(),
            'units' => $this->units(),
        ]);
    }

    public function update(StoreServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        return redirect()->route('services.edit', $service)->with('success', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()->route('services.index')->with('success', 'Service removed.');
    }

    public function storeConsumable(Request $request, Service $service): RedirectResponse
    {
        $data = $request->validate([
            'inventory_item_id' => ['required', 'string', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_id' => ['required', 'string', 'exists:units,id'],
            'is_optional' => ['boolean'],
        ]);

        $service->consumables()->updateOrCreate(
            ['inventory_item_id' => $data['inventory_item_id']],
            $data,
        );

        return back()->with('success', 'Consumable added.');
    }

    public function destroyConsumable(Service $service, string $consumable): RedirectResponse
    {
        $service->consumables()->whereKey($consumable)->delete();

        return back()->with('success', 'Consumable removed.');
    }

    /** @return \Illuminate\Support\Collection<int, array{value:string, label:string}> */
    private function categories()
    {
        return ServiceCategory::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]);
    }

    /** @return \Illuminate\Support\Collection<int, array{value:string, label:string}> */
    private function items()
    {
        return InventoryItem::query()->where('is_active', true)->orderBy('name')
            ->get(['id', 'name'])->map(fn ($i) => ['value' => $i->id, 'label' => $i->name]);
    }

    /** @return \Illuminate\Support\Collection<int, array{value:string, label:string}> */
    private function units()
    {
        return Unit::query()->orderBy('name')->get(['id', 'abbreviation', 'name'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->abbreviation})"]);
    }
}
