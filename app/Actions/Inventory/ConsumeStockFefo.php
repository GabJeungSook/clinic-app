<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Support\Inventory\StockLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Consume stock of an item using First-Expiry-First-Out allocation.
 *
 * For batch-tracked items it draws from the batch with the nearest expiry
 * first (batches with no expiry come last), spanning multiple batches until the
 * requested quantity is met — emitting one ledger movement per batch touched so
 * cost and expiry remain traceable. Refuses when there is not enough stock
 * unless $allowOverride is set (e.g. a supervisor forcing a correction).
 */
class ConsumeStockFefo
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @return array<int, StockMovement> movements created
     */
    public function handle(
        InventoryItem $item,
        float $quantityBase,
        ?Model $reference = null,
        ?int $performedBy = null,
        bool $allowOverride = false,
        ?\DateTimeInterface $occurredAt = null,
        ?string $reason = null,
        MovementType $type = MovementType::SessionConsume,
    ): array {
        $needed = round(abs($quantityBase), 3);

        if ($needed <= 0) {
            return [];
        }

        return DB::transaction(function () use ($item, $needed, $reference, $performedBy, $allowOverride, $occurredAt, $reason, $type) {
            $available = $item->stockOnHand();

            if ($needed > $available && ! $allowOverride) {
                throw InsufficientStockException::for($item->name, $needed, $available);
            }

            // Non-batch-tracked: a single movement with no batch.
            if (! $item->is_batch_tracked) {
                return [$this->ledger->post(
                    item: $item,
                    type: $type,
                    absoluteQuantity: $needed,
                    reference: $reference,
                    performedBy: $performedBy,
                    reason: $reason,
                    occurredAt: $occurredAt,
                )];
            }

            $movements = [];
            $remaining = $needed;

            $batches = $item->batches()
                ->orderByRaw('expiry_date IS NULL')   // non-null expiries first
                ->orderBy('expiry_date')
                ->orderBy('received_at')
                ->get();

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $batchRemaining = $batch->qtyRemaining();
                if ($batchRemaining <= 0) {
                    continue;
                }

                $draw = min($remaining, $batchRemaining);

                $movements[] = $this->ledger->post(
                    item: $item,
                    type: $type,
                    absoluteQuantity: $draw,
                    batch: $batch,
                    reference: $reference,
                    performedBy: $performedBy,
                    reason: $reason,
                    unitCost: (float) $batch->unit_cost,
                    occurredAt: $occurredAt,
                );

                $remaining = round($remaining - $draw, 3);
            }

            // Override path: post any shortfall with no batch so on-hand stays correct.
            if ($remaining > 0 && $allowOverride) {
                $movements[] = $this->ledger->post(
                    item: $item,
                    type: $type,
                    absoluteQuantity: $remaining,
                    reference: $reference,
                    performedBy: $performedBy,
                    reason: $reason ?? 'Override: consumed beyond tracked batches',
                    occurredAt: $occurredAt,
                );
            }

            return $movements;
        });
    }
}
