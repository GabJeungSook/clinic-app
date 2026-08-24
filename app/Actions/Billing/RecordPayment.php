<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Record a payment (or a refund, via a negative amount) against an invoice, then
 * recompute the cached amount_paid and derive the invoice status from it.
 */
class RecordPayment
{
    public function handle(
        Invoice $invoice,
        float $amount,
        PaymentMethod $method = PaymentMethod::Cash,
        ?string $reference = null,
        ?int $receivedBy = null,
        ?\DateTimeInterface $paidAt = null,
        ?string $notes = null,
    ): Payment {
        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $receivedBy, $paidAt, $notes) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'method' => $method,
                'amount' => round($amount, 2),
                'reference' => $reference,
                'received_by' => $receivedBy,
                'paid_at' => $paidAt ?? now(),
                'notes' => $notes,
            ]);

            $paid = round((float) $invoice->payments()->sum('amount'), 2);
            $invoice->forceFill([
                'amount_paid' => $paid,
                'status' => $this->deriveStatus($invoice, $paid),
            ])->save();

            return $payment;
        });
    }

    private function deriveStatus(Invoice $invoice, float $paid): InvoiceStatus
    {
        // Preserve terminal states.
        if (in_array($invoice->status, [InvoiceStatus::Void, InvoiceStatus::Refunded], true)) {
            return $invoice->status;
        }

        $grand = round((float) $invoice->grand_total, 2);

        if ($paid <= 0) {
            return InvoiceStatus::Unpaid;
        }
        if ($paid + 0.001 >= $grand) {
            return InvoiceStatus::Paid;
        }

        return InvoiceStatus::PartiallyPaid;
    }
}
