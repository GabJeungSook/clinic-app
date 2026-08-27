<?php

namespace App\Http\Controllers;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Inventory\WriteOffExpiredStock;
use App\Enums\ItemType;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Models\Batch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Unit;
use App\Support\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'low');

        $items = InventoryItem::query()
            ->with('baseUnit:id,abbreviation', 'category:id,name')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")));

        // Sorting — low stock first by default so items needing attention float up.
        match ($sort) {
            'name' => $items->orderBy('name'),
            'stock_asc' => $items->orderBy('stock_on_hand_cache')->orderBy('name'),
            'stock_desc' => $items->orderByDesc('stock_on_hand_cache')->orderBy('name'),
            default => $items->orderByRaw('CASE WHEN stock_on_hand_cache <= reorder_level THEN 0 ELSE 1 END')->orderBy('name'),
        };

        $items = $items
            ->paginate(15)
            ->withQueryString()
            ->through(fn (InventoryItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'sku' => $i->sku,
                'type' => $i->type->value,
                'type_label' => $i->type->label(),
                'unit' => $i->baseUnit?->abbreviation,
                'category' => $i->category?->name,
                'on_hand' => (float) $i->stock_on_hand_cache,
                'reorder_level' => (float) $i->reorder_level,
                'is_low' => $i->isLowStock(),
                'is_negative' => (float) $i->stock_on_hand_cache < 0,
                'is_active' => (bool) $i->is_active,
            ]);

        return Inertia::render('Inventory/Index', [
            'items' => $items,
            'filters' => ['search' => $search, 'sort' => $sort],
            'expiryThresholdDays' => (int) Settings::get('inventory.expiry_threshold_days', 30),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Create', $this->formData());
    }

    public function store(StoreInventoryItemRequest $request, ReceiveStock $receiveStock): RedirectResponse
    {
        $data = $request->validated();
        $item = InventoryItem::create($this->itemAttributes($data));

        // Optional opening stock.
        if (! empty($data['opening_qty']) && (float) $data['opening_qty'] > 0) {
            $receiveStock->handle(
                $item,
                (float) $data['opening_qty'],
                expiryDate: ! empty($data['opening_expiry']) ? new \DateTimeImmutable($data['opening_expiry']) : null,
                batchNumber: 'OPENING',
                unitCost: (float) ($data['opening_unit_cost'] ?? 0),
                performedBy: $request->user()?->id,
            );
        }

        return redirect()->route('inventory.show', $item)->with('success', 'Item created.');
    }

    public function show(InventoryItem $inventory): Response
    {
        $item = $inventory->load('baseUnit:id,abbreviation', 'category:id,name');

        $batches = $item->batches()
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get()
            ->map(fn (Batch $b) => [
                'id' => $b->id,
                'batch_number' => $b->batch_number,
                'expiry_date' => $b->expiry_date?->toDateString(),
                'remaining' => (float) $b->qty_remaining_cache,
                'is_expired' => $b->isExpired(),
            ])
            ->filter(fn ($b) => $b['remaining'] > 0 || $b['is_expired'])
            ->values();

        $movements = $item->movements()
            ->with('performer:id,name')
            ->latest('occurred_at')
            ->limit(30)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'quantity' => (float) $m->quantity,
                'reason' => $m->reason,
                'occurred_at' => $m->occurred_at?->toDateTimeString(),
                'by' => $m->performer?->name,
            ]);

        return Inertia::render('Inventory/Show', [
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'type_label' => $item->type->label(),
                'category' => $item->category?->name,
                'unit' => $item->baseUnit?->abbreviation,
                'on_hand' => $item->stockOnHand(),
                'reorder_level' => (float) $item->reorder_level,
                'is_low' => $item->isLowStock(),
                'is_batch_tracked' => (bool) $item->is_batch_tracked,
                'track_expiry' => (bool) $item->track_expiry,
                'is_active' => (bool) $item->is_active,
            ],
            'batches' => $batches,
            'movements' => $movements,
        ]);
    }

    public function edit(InventoryItem $inventory): Response
    {
        return Inertia::render('Inventory/Edit', [
            ...$this->formData(),
            'item' => $inventory->only([
                'id', 'name', 'sku', 'barcode', 'type', 'inventory_category_id',
                'base_unit_id', 'is_batch_tracked', 'track_expiry',
                'reorder_level', 'reorder_qty', 'default_sell_price', 'is_active',
            ]),
        ]);
    }

    public function update(StoreInventoryItemRequest $request, InventoryItem $inventory): RedirectResponse
    {
        $inventory->update($this->itemAttributes($request->validated()));

        return redirect()->route('inventory.show', $inventory)->with('success', 'Item updated.');
    }

    public function destroy(InventoryItem $inventory): RedirectResponse
    {
        $inventory->delete();

        return redirect()->route('inventory.index')->with('success', 'Item removed.');
    }

    public function receiveStock(Request $request, InventoryItem $inventory, ReceiveStock $receiveStock): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'batch_number' => ['nullable', 'string', 'max:60'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $receiveStock->handle(
            $inventory,
            (float) $data['quantity'],
            expiryDate: ! empty($data['expiry_date']) ? new \DateTimeImmutable($data['expiry_date']) : null,
            batchNumber: $data['batch_number'] ?? null,
            unitCost: (float) ($data['unit_cost'] ?? 0),
            performedBy: $request->user()?->id,
        );

        return back()->with('success', 'Stock added.');
    }

    public function adjustStock(Request $request, InventoryItem $inventory, AdjustStock $adjustStock): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'max:150'],
        ]);

        $adjustStock->handle($inventory, (float) $data['quantity'], $data['reason'], performedBy: $request->user()?->id);

        return back()->with('success', 'Stock adjusted.');
    }

    public function writeOff(Request $request, InventoryItem $inventory, string $batch, WriteOffExpiredStock $writeOff): RedirectResponse
    {
        $batchModel = $inventory->batches()->whereKey($batch)->firstOrFail();
        $writeOff->handle($batchModel, performedBy: $request->user()?->id);

        return back()->with('success', 'Batch written off.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'units' => Unit::query()->orderBy('name')->get(['id', 'abbreviation', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->abbreviation})"]),
            'types' => collect(ItemType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function itemAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'type' => $data['type'],
            'inventory_category_id' => $data['inventory_category_id'] ?? null,
            'base_unit_id' => $data['base_unit_id'],
            'is_batch_tracked' => (bool) ($data['is_batch_tracked'] ?? false),
            'track_expiry' => (bool) ($data['track_expiry'] ?? false),
            'reorder_level' => $data['reorder_level'],
            'reorder_qty' => $data['reorder_qty'] ?? 0,
            'default_sell_price' => $data['default_sell_price'] ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
