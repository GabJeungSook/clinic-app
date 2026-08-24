<?php

namespace App\Actions\System;

use App\Models\Batch;
use App\Models\InventoryItem;
use App\Models\Invoice;

/**
 * Recompute the denormalised caches (item on-hand, batch remaining, invoice
 * amount paid) from their authoritative ledgers and report any drift. Intended
 * to run nightly as a safety check against the derived-vs-cached values.
 */
class ReconcileCaches
{
    /**
     * @return array{items:int, batches:int, invoices:int, drift:array<int, string>}
     */
    public function handle(): array
    {
        $drift = [];

        $items = 0;
        InventoryItem::withoutBranchScope()->chunkById(200, function ($chunk) use (&$items, &$drift) {
            foreach ($chunk as $item) {
                $cached = (float) $item->stock_on_hand_cache;
                $truth = $item->stockOnHand();
                if (abs($cached - $truth) > 0.0001) {
                    $drift[] = "Item {$item->name}: cache {$cached} vs ledger {$truth}";
                    $item->refreshStockCache();
                }
                $items++;
            }
        });

        $batches = 0;
        Batch::withoutBranchScope()->chunkById(200, function ($chunk) use (&$batches, &$drift) {
            foreach ($chunk as $batch) {
                $cached = (float) $batch->qty_remaining_cache;
                $truth = $batch->qtyRemaining();
                if (abs($cached - $truth) > 0.0001) {
                    $drift[] = "Batch {$batch->batch_number}: cache {$cached} vs ledger {$truth}";
                    $batch->refreshRemainingCache();
                }
                $batches++;
            }
        });

        $invoices = 0;
        Invoice::withoutBranchScope()->with('payments')->chunkById(200, function ($chunk) use (&$invoices, &$drift) {
            foreach ($chunk as $invoice) {
                $cached = (float) $invoice->amount_paid;
                $truth = round((float) $invoice->payments->sum('amount'), 2);
                if (abs($cached - $truth) > 0.0001) {
                    $drift[] = "Invoice {$invoice->invoice_no}: paid cache {$cached} vs {$truth}";
                    $invoice->forceFill(['amount_paid' => $truth])->saveQuietly();
                }
                $invoices++;
            }
        });

        return compact('items', 'batches', 'invoices', 'drift');
    }
}
