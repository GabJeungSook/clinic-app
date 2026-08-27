<?php

namespace App\Actions\Purchasing;

use App\Enums\PurchaseStatus;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;

/**
 * Build a DRAFT purchase order containing every active item that is low on stock
 * or oversold, so the manager doesn't have to add each line by hand.
 *
 *   - Quantity defaults to the item's reorder_qty; if that isn't set, it orders
 *     just enough to climb back above the reorder level (minimum 1).
 *   - Unit is the item's base unit; cost is pre-filled from the item's most
 *     recent batch so the total is a sensible estimate.
 *
 * Nothing is ordered automatically — the draft is opened for review first.
 * Returns null when nothing needs reordering.
 */
class DraftReorderPurchase
{
    public function handle(?int $createdBy = null): ?Purchase
    {
        $items = InventoryItem::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('reorder_level', '>', 0)->whereColumn('stock_on_hand_cache', '<=', 'reorder_level'))
                ->orWhere('stock_on_hand_cache', '<', 0))
            ->with(['batches' => fn ($q) => $q->latest('received_at')->limit(1)])
            ->orderBy('name')
            ->get();

        if ($items->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($items, $createdBy) {
            $purchase = Purchase::create([
                'reference_no' => DocumentNumber::next(Purchase::query(), 'PO', 'reference_no'),
                'status' => PurchaseStatus::Draft,
                'ordered_at' => now(),
                'notes' => 'Auto-drafted from low-stock items — review quantities and supplier before ordering.',
                'created_by' => $createdBy,
            ]);

            $total = 0.0;

            foreach ($items as $item) {
                $onHand = (float) $item->stock_on_hand_cache;
                $reorderQty = (float) $item->reorder_qty;
                $reorderLevel = (float) $item->reorder_level;

                // Prefer the configured reorder quantity; otherwise order enough
                // to get back above the reorder level (never less than 1).
                $qty = round($reorderQty > 0 ? $reorderQty : max($reorderLevel - $onHand, 1), 3);

                $unitCost = (float) ($item->batches->first()?->unit_cost ?? 0);

                $purchase->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_id' => $item->base_unit_id,
                    'unit_cost' => $unitCost,
                ]);

                $total += $qty * $unitCost;
            }

            $purchase->update(['total_cost' => round($total, 2)]);

            return $purchase;
        });
    }
}
