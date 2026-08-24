<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\StockMovement;
use App\Support\Inventory\StockLedger;

/**
 * Write off the remaining quantity of an expired (or damaged) batch, zeroing it
 * out of on-hand stock while leaving the full audit trail intact.
 */
class WriteOffExpiredStock
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(Batch $batch, ?int $performedBy = null, ?string $reason = null): ?StockMovement
    {
        $remaining = $batch->qtyRemaining();

        if ($remaining <= 0) {
            return null;
        }

        return $this->ledger->post(
            item: $batch->item,
            type: MovementType::ExpiryWriteoff,
            absoluteQuantity: $remaining,
            batch: $batch,
            performedBy: $performedBy,
            reason: $reason ?? 'Expired stock write-off',
        );
    }
}
