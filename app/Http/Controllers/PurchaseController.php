<?php

namespace App\Http\Controllers;

use App\Actions\Purchasing\DraftReorderPurchase;
use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\PurchaseStatus;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Support\DocumentNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $purchases = Purchase::query()
            ->with('supplier:id,name')
            ->withCount('items')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('reference_no', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$search}%"))))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Purchase $p) => [
                'id' => $p->id,
                'reference_no' => $p->reference_no,
                'supplier' => $p->supplier?->name,
                'status' => $p->status->value,
                'items' => $p->items_count,
                'total_cost' => (float) $p->total_cost,
                'received_at' => $p->received_at?->toDateString(),
                'created_at' => $p->created_at?->toDateString(),
            ]);

        return Inertia::render('Purchasing/Index', [
            'purchases' => $purchases,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Purchasing/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name]),
            'items' => InventoryItem::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'base_unit_id'])
                ->map(fn ($i) => ['value' => $i->id, 'label' => $i->name]),
            'units' => Unit::query()->orderBy('name')->get(['id', 'abbreviation', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->abbreviation})"]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'string', 'exists:suppliers,id'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'string', 'exists:inventory_items,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_id' => ['required', 'string', 'exists:units,id'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ]);

        $purchase = DB::transaction(function () use ($data, $request) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? DocumentNumber::next(Purchase::query(), 'PO', 'reference_no'),
                'status' => PurchaseStatus::Draft,
                'ordered_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $total = 0;
            foreach ($data['lines'] as $line) {
                $purchase->items()->create($line);
                $total += (float) $line['quantity'] * (float) $line['unit_cost'];
            }
            $purchase->update(['total_cost' => round($total, 2)]);

            return $purchase;
        });

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase saved.');
    }

    public function reorder(Request $request, DraftReorderPurchase $draft): RedirectResponse
    {
        $purchase = $draft->handle($request->user()?->id);

        if ($purchase === null) {
            return back()->with('error', 'Nothing needs reordering right now.');
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', 'Draft order created from low-stock items. Review the quantities and supplier, then receive when it arrives.');
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load('supplier:id,name', 'items.item:id,name', 'items.unit:id,abbreviation');

        return Inertia::render('Purchasing/Show', [
            'purchase' => [
                'id' => $purchase->id,
                'reference_no' => $purchase->reference_no,
                'supplier' => $purchase->supplier?->name,
                'status' => $purchase->status->value,
                'total_cost' => (float) $purchase->total_cost,
                'notes' => $purchase->notes,
                'received_at' => $purchase->received_at?->toDateString(),
                'can_receive' => $purchase->status !== PurchaseStatus::Received
                    && $purchase->status !== PurchaseStatus::Cancelled,
            ],
            'lines' => $purchase->items->map(fn ($l) => [
                'item' => $l->item?->name,
                'quantity' => (float) $l->quantity,
                'unit' => $l->unit?->abbreviation,
                'unit_cost' => (float) $l->unit_cost,
                'batch_number' => $l->batch_number,
                'expiry_date' => $l->expiry_date?->toDateString(),
            ]),
        ]);
    }

    public function receive(Request $request, Purchase $purchase, ReceivePurchase $receive): RedirectResponse
    {
        if (in_array($purchase->status, [PurchaseStatus::Received, PurchaseStatus::Cancelled], true)) {
            return back()->with('error', 'Purchase already finalised.');
        }

        $receive->handle($purchase, $request->user()?->id);

        return back()->with('success', 'Stock received and added to inventory.');
    }
}
