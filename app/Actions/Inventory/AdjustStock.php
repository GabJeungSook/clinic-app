<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Support\Inventory\StockLedger;

/**
 * Manual stock correction (stock take, breakage, found stock). Positive
 * quantity = increase, negative = decrease. Always requires a reason.
 */
class AdjustStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(
        InventoryItem $item,
        float $quantityBase,
        string $reason,
        ?Batch $batch = null,
        ?int $performedBy = null,
    ): StockMovement {
        $type = $quantityBase >= 0 ? MovementType::AdjustmentIn : MovementType::AdjustmentOut;

        return $this->ledger->post(
            item: $item,
            type: $type,
            absoluteQuantity: abs($quantityBase),
            batch: $batch,
            performedBy: $performedBy,
            reason: $reason,
        );
    }
}
