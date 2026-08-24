<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $suppliers = Supplier::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'contact_name' => $s->contact_name,
                'phone' => $s->phone,
                'email' => $s->email,
                'is_active' => (bool) $s->is_active,
            ]);

        return Inertia::render('Purchasing/Suppliers', [
            'suppliers' => $suppliers,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request));

        return back()->with('success', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('success', 'Supplier removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
