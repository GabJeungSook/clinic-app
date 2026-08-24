<?php

namespace App\Actions\Purchasing;

use App\Actions\Inventory\ReceiveStock;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Mark a purchase as received: for every line create a batch and a purchase_in
 * movement (converting the line's unit to the item base unit), then stamp the
 * purchase received and recompute its total cost.
 */
class ReceivePurchase
{
    public function __construct(private readonly ReceiveStock $receiveStock) {}

    public function handle(Purchase $purchase, ?int $performedBy = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $performedBy) {
            $purchase->loadMissing('items.item', 'items.unit');
            $total = 0.0;

            foreach ($purchase->items as $line) {
                $unit = $line->unit ?? Unit::find($line->unit_id);
                $factor = $unit ? (float) $unit->factor_to_base : 1.0;
                $qtyBase = (float) $line->quantity * $factor;

                // Unit cost is per purchase unit; convert to per base unit for the ledger.
                $unitCostBase = $factor > 0 ? ((float) $line->unit_cost / $factor) : (float) $line->unit_cost;

                $this->receiveStock->handle(
                    item: $line->item,
                    quantityBase: $qtyBase,
                    expiryDate: $line->expiry_date,
                    batchNumber: $line->batch_number,
                    unitCost: $unitCostBase,
                    supplier: $purchase->supplier,
                    purchaseItem: $line,
                    performedBy: $performedBy,
                    occurredAt: now(),
                );

                $total += (float) $line->quantity * (float) $line->unit_cost;
            }

            $purchase->forceFill([
                'status' => PurchaseStatus::Received,
                'received_at' => now(),
                'total_cost' => round($total, 2),
            ])->save();

            return $purchase->fresh();
        });
    }
}
