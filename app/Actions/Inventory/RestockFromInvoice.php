<?php

namespace App\Actions\Inventory;

use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\StockMovement;
use App\Support\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

/**
 * Return the retail stock that a sale (invoice) took out of inventory — used when
 * an invoice is voided or a sale is refunded with the goods returned.
 *
 * Works off the immutable ledger: for every SaleOut movement traced to the
 * invoice it posts an offsetting ReturnIn (same item, same batch, same quantity),
 * so batch-level on-hand and expiry tracking stay exact. Idempotent — it only
 * returns the portion not already returned, so voiding after a partial refund
 * (or a double click) never double-counts.
 *
 * @return array<int, StockMovement> the ReturnIn movements it created
 */
class RestockFromInvoice
{
    public function __construct(private readonly StockLedger $ledger) {}

    public function handle(Invoice $invoice, ?int $performedBy = null, string $reason = 'Sale reversed'): array
    {
        return DB::transaction(function () use ($invoice, $performedBy, $reason) {
            $moves = StockMovement::query()
                ->where('reference_type', $invoice->getMorphClass())
                ->where('reference_id', $invoice->getKey())
                ->whereIn('type', [MovementType::SaleOut, MovementType::ReturnIn])
                ->get();

            // Net still-outstanding quantity per item+batch = sold − already returned.
            $sold = [];      // key => abs qty taken out
            $returned = [];  // key => abs qty already put back
            $meta = [];      // key => [item_id, batch_id, unit_cost]
            foreach ($moves as $m) {
                $key = $m->inventory_item_id . '|' . ($m->batch_id ?? '');
                $qty = abs((float) $m->quantity);
                if ($m->type === MovementType::SaleOut) {
                    $sold[$key] = ($sold[$key] ?? 0) + $qty;
                    $meta[$key] ??= ['item' => $m->inventory_item_id, 'batch' => $m->batch_id, 'cost' => $m->unit_cost];
                } else {
                    $returned[$key] = ($returned[$key] ?? 0) + $qty;
                }
            }

            $created = [];
            foreach ($sold as $key => $soldQty) {
                $remaining = round($soldQty - ($returned[$key] ?? 0), 3);
                if ($remaining <= 0) {
                    continue;
                }

                $item = InventoryItem::find($meta[$key]['item']);
                if (! $item) {
                    continue;
                }
                $batch = $meta[$key]['batch'] ? Batch::find($meta[$key]['batch']) : null;

                $created[] = $this->ledger->post(
                    item: $item,
                    type: MovementType::ReturnIn,
                    absoluteQuantity: $remaining,
                    batch: $batch,
                    reference: $invoice,
                    performedBy: $performedBy,
                    reason: $reason,
                    unitCost: $meta[$key]['cost'] !== null ? (float) $meta[$key]['cost'] : null,
                );
            }

            return $created;
        });
    }
}
