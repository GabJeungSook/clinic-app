<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $units = Unit::query()->with('baseUnit:id,abbreviation')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('abbreviation', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Unit $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'abbreviation' => $u->abbreviation,
                'base_unit_id' => $u->base_unit_id,
                'base' => $u->baseUnit?->abbreviation,
                'factor_to_base' => (float) $u->factor_to_base,
            ]);

        return Inertia::render('Inventory/Units', [
            'units' => $units,
            'filters' => ['search' => $search],
            'baseOptions' => Unit::query()->whereNull('base_unit_id')->orderBy('name')->get(['id', 'name', 'abbreviation'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->abbreviation})"]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Unit::create($this->validated($request));

        return back()->with('success', 'Unit added.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $unit->update($this->validated($request));

        return back()->with('success', 'Unit updated.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $unit->delete();

        return back()->with('success', 'Unit removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'abbreviation' => ['required', 'string', 'max:20'],
            'base_unit_id' => ['nullable', 'string', 'exists:units,id'],
            'factor_to_base' => ['required', 'numeric', 'min:0.000001'],
        ]);

        // A base unit references nothing and has factor 1.
        if (empty($data['base_unit_id'])) {
            $data['base_unit_id'] = null;
            $data['factor_to_base'] = 1;
        }

        return $data;
    }
}
