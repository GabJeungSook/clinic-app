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
 *
 * Already-expired batches are NEVER drawn from (they must be written off, not
 * used on a patient). Pass $excludeExpired = false only for internal corrections
 * that deliberately need to touch expired stock.
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
        bool $excludeExpired = true,
    ): array {
        $needed = round(abs($quantityBase), 3);

        if ($needed <= 0) {
            return [];
        }

        return DB::transaction(function () use ($item, $needed, $reference, $performedBy, $allowOverride, $occurredAt, $reason, $type, $excludeExpired) {
            // "Usable" stock ignores expired batches; that is what we can draw on.
            $available = $excludeExpired ? $item->usableStockOnHand() : $item->stockOnHand();

            if ($needed > $available && ! $allowOverride) {
                // Distinguish "genuinely out of stock" from "only expired stock left",
                // so the front desk knows to write it off / reorder rather than guess.
                if ($excludeExpired && $item->stockOnHand() >= $needed) {
                    throw new InsufficientStockException(sprintf(
                        'Cannot use "%s": the remaining stock is expired. Write off the expired batch(es) or receive fresh stock first.',
                        $item->name,
                    ));
                }

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
                ->when($excludeExpired, fn ($q) => $q->where(fn ($w) => $w
                    ->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now())))
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
