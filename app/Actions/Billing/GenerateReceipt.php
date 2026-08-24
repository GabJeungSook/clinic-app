<?php

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Receipt;
use App\Support\DocumentNumber;
use App\Support\Settings\Settings;

/**
 * Produce a receipt with a frozen snapshot of the invoice totals, lines and
 * payments, so a reprinted receipt always matches what was issued.
 */
class GenerateReceipt
{
    public function handle(Invoice $invoice, ?\DateTimeInterface $printedAt = null): Receipt
    {
        $invoice->loadMissing('items', 'payments', 'patient');

        $snapshot = [
            'clinic' => [
                'name' => Settings::get('clinic.name'),
                'address' => Settings::get('clinic.address'),
                'phone' => Settings::get('clinic.phone'),
                'footer' => Settings::get('clinic.receipt_footer'),
            ],
            'currency' => Settings::get('billing.currency'),
            'invoice_no' => $invoice->invoice_no,
            'issued_at' => optional($invoice->issued_at)->toIso8601String(),
            'patient' => $invoice->patient?->full_name,
            'items' => $invoice->items->map(fn ($i) => [
                'description' => $i->description_snapshot,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'discount' => (float) $i->discount,
                'tax' => (float) $i->tax,
                'line_total' => (float) $i->line_total,
            ])->all(),
            'totals' => [
                'subtotal' => (float) $invoice->subtotal,
                'discount_total' => (float) $invoice->discount_total,
                'tax_total' => (float) $invoice->tax_total,
                'grand_total' => (float) $invoice->grand_total,
                'amount_paid' => (float) $invoice->amount_paid,
                'tax_enabled' => (bool) $invoice->tax_enabled,
                'tax_rate' => (float) $invoice->tax_rate_snapshot,
                'tax_inclusive' => (bool) $invoice->tax_inclusive,
            ],
            'payments' => $invoice->payments->map(fn ($p) => [
                'method' => $p->method->value,
                'amount' => (float) $p->amount,
                'paid_at' => optional($p->paid_at)->toIso8601String(),
            ])->all(),
        ];

        return Receipt::create([
            'invoice_id' => $invoice->id,
            'receipt_no' => DocumentNumber::next(Receipt::query(), 'OR', 'receipt_no'),
            'printed_at' => $printedAt ?? now(),
            'snapshot' => $snapshot,
        ]);
    }
}
