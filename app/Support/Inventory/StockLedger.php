<?php

namespace App\Support\Inventory;

use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;

/**
 * Low-level writer for the immutable stock ledger.
 *
 * Every stock change in the system funnels through post(): it records a signed
 * movement and keeps the item/batch on-hand caches in step. Callers pass an
 * ABSOLUTE quantity (in the item base unit); the sign is derived from the
 * movement type so no caller can accidentally add when it meant to subtract.
 */
class StockLedger
{
    public function post(
        InventoryItem $item,
        MovementType $type,
        float $absoluteQuantity,
        ?Batch $batch = null,
        ?Model $reference = null,
        ?int $performedBy = null,
        ?string $reason = null,
        ?float $unitCost = null,
        ?\DateTimeInterface $occurredAt = null,
    ): StockMovement {
        $signed = round(abs($absoluteQuantity) * $type->sign(), 3);

        $movement = new StockMovement([
            'inventory_item_id' => $item->id,
            'batch_id' => $batch?->id,
            'type' => $type,
            'quantity' => $signed,
            'unit_id' => $item->base_unit_id,
            'unit_cost' => $unitCost,
            'performed_by' => $performedBy,
            'reason' => $reason,
            'occurred_at' => $occurredAt ?? now(),
        ]);

        if ($reference !== null) {
            $movement->reference()->associate($reference);
        }

        $movement->save();

        // Keep denormalised caches aligned with the ledger truth.
        $item->refreshStockCache();
        $batch?->refreshRemainingCache();

        return $movement;
    }
}
