<?php

namespace App\Actions\Billing;

use App\Actions\Inventory\RestockFromInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refund money already received on an invoice. Records a negative payment (so the
 * cash-basis revenue report nets it out automatically), recomputes the balance,
 * and — when the whole invoice has been returned — marks it Refunded (terminal).
 * Optionally returns the retail goods to stock.
 */
class RefundInvoice
{
    public function __construct(private readonly RestockFromInvoice $restock) {}

    public function handle(
        Invoice $invoice,
        float $amount,
        PaymentMethod $method = PaymentMethod::Cash,
        ?string $reason = null,
        bool $restock = false,
        ?int $performedBy = null,
    ): Payment {
        $amount = round(abs($amount), 2);
        $paid = round((float) $invoice->amount_paid, 2);

        if (in_array($invoice->status, [InvoiceStatus::Void, InvoiceStatus::Refunded], true)) {
            throw ValidationException::withMessages(['amount' => 'This invoice is already closed.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a refund amount greater than zero.']);
        }
        if ($amount > $paid) {
            throw ValidationException::withMessages(['amount' => "Cannot refund more than the {$paid} already paid."]);
        }

        return DB::transaction(function () use ($invoice, $amount, $method, $reason, $restock, $performedBy) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'method' => $method,
                'amount' => -$amount,
                'reference' => null,
                'received_by' => $performedBy,
                'paid_at' => now(),
                'notes' => $reason ? 'Refund: '.$reason : 'Refund',
            ]);

            $newPaid = round((float) $invoice->payments()->sum('amount'), 2);
            $grand = round((float) $invoice->grand_total, 2);

            // Fully returned → terminal Refunded; otherwise fall back to the
            // normal paid/partial/unpaid derivation.
            $status = $newPaid <= 0.001
                ? InvoiceStatus::Refunded
                : ($newPaid + 0.001 >= $grand ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid);

            $invoice->forceFill(['amount_paid' => $newPaid, 'status' => $status])->save();

            if ($restock) {
                $this->restock->handle($invoice, performedBy: $performedBy, reason: 'Refund'.($reason ? ': '.$reason : ''));
            }

            return $payment;
        });
    }
}
