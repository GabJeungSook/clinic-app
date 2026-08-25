<?php

namespace App\Actions\Billing;

use App\Actions\Inventory\RestockFromInvoice;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Void (cancel) an invoice that should never have been billed — a duplicate, a
 * mis-keyed sale, a cancelled walk-in. A void reverses the retail stock the sale
 * removed and marks the invoice terminal so it drops out of the sales report.
 *
 * Money guard: a void must not leave cash unaccounted for, so an invoice with any
 * payment on it cannot be voided — refund it to a zero balance first, then void.
 */
class VoidInvoice
{
    public function __construct(private readonly RestockFromInvoice $restock) {}

    public function handle(Invoice $invoice, string $reason, ?int $voidedBy = null): Invoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Void, InvoiceStatus::Refunded], true)) {
            throw ValidationException::withMessages(['status' => 'This invoice is already closed.']);
        }

        if (round((float) $invoice->amount_paid, 2) !== 0.0) {
            throw ValidationException::withMessages([
                'status' => 'Refund the payments to a zero balance before voiding this invoice.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $reason, $voidedBy) {
            // Put back any retail stock this sale removed.
            $this->restock->handle($invoice, performedBy: $voidedBy, reason: 'Void: '.$reason);

            $invoice->forceFill([
                'status' => InvoiceStatus::Void,
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => $voidedBy,
            ])->save();

            return $invoice->fresh();
        });
    }
}
