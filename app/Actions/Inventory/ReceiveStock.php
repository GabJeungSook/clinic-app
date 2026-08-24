<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\InventoryItem;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Support\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Receive stock of an item into a new batch and record the inflow movement.
 * Used for purchase receipts and opening balances. Quantity is in the item
 * base unit.
 */
class ReceiveStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(
        InventoryItem $item,
        float $quantityBase,
        ?\DateTimeInterface $expiryDate = null,
        ?string $batchNumber = null,
        float $unitCost = 0,
        ?Supplier $supplier = null,
        ?PurchaseItem $purchaseItem = null,
        ?int $performedBy = null,
        MovementType $type = MovementType::PurchaseIn,
        ?\DateTimeInterface $occurredAt = null,
    ): Batch {
        $qty = round(abs($quantityBase), 3);

        return DB::transaction(function () use (
            $item, $qty, $expiryDate, $batchNumber, $unitCost, $supplier, $purchaseItem, $performedBy, $type, $occurredAt
        ) {
            $batch = Batch::create([
                'inventory_item_id' => $item->id,
                'supplier_id' => $supplier?->id,
                'purchase_item_id' => $purchaseItem?->id,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'received_at' => $occurredAt ?? now(),
                'unit_cost' => $unitCost,
                'initial_quantity' => $qty,
            ]);

            $this->ledger->post(
                item: $item,
                type: $type,
                absoluteQuantity: $qty,
                batch: $batch,
                reference: $purchaseItem,
                performedBy: $performedBy,
                unitCost: $unitCost,
                occurredAt: $occurredAt,
            );

            return $batch->fresh();
        });
    }
}
