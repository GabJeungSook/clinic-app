<?php

namespace App\Actions\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Promotion;
use App\Support\Billing\InvoiceCalculator;
use App\Support\DocumentNumber;
use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Build and persist an invoice from a set of line inputs, applying line-level
 * and whole-invoice promotions and snapshotting the tax configuration so the
 * document is immutable against later settings changes.
 *
 * Each line input:
 *   ['description' => string, 'quantity' => float, 'unit_price' => float,
 *    'discount' => float (manual), 'promotion_id' => ?string, 'itemable' => ?Model]
 */
class CreateInvoice
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function handle(
        ?Patient $patient,
        array $lines,
        ?string $invoicePromotionId = null,
        float $manualInvoiceDiscount = 0,
        InvoiceStatus $status = InvoiceStatus::Unpaid,
        ?int $createdBy = null,
        ?string $notes = null,
        ?\DateTimeInterface $issuedAt = null,
    ): Invoice {
        return DB::transaction(function () use (
            $patient, $lines, $invoicePromotionId, $manualInvoiceDiscount, $status, $createdBy, $notes, $issuedAt
        ) {
            $taxEnabled = Settings::taxEnabled();
            $taxRate = Settings::taxRate();
            $taxInclusive = Settings::taxInclusive();

            $usedPromotions = [];
            $prepared = [];   // per-line computed data
            $lineNets = [];

            foreach ($lines as $i => $line) {
                $qty = (float) ($line['quantity'] ?? 1);
                $price = (float) ($line['unit_price'] ?? 0);
                $base = round($qty * $price, 2);
                $manual = round((float) ($line['discount'] ?? 0), 2);

                $promoDiscount = 0.0;
                $promoSnapshot = null;
                $promotionId = $line['promotion_id'] ?? null;

                if ($promotionId) {
                    $promo = Promotion::find($promotionId);
                    if ($promo && $promo->isValidOn() && ($base - $manual) >= (float) ($promo->min_spend ?? 0)) {
                        $promoDiscount = $promo->discountFor(max(0, $base - $manual));
                        $promoSnapshot = ['id' => $promo->id, 'name' => $promo->name, 'type' => $promo->type->value, 'value' => (float) $promo->value];
                        $usedPromotions[$promo->id] = $promo;
                    } else {
                        $promotionId = null;
                    }
                }

                $discount = round($manual + $promoDiscount, 2);
                $net = round(max(0, $base - $discount), 2);
                $lineNets[$i] = $net;

                $prepared[$i] = [
                    'itemable' => $line['itemable'] ?? null,
                    'description' => (string) ($line['description'] ?? 'Item'),
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount' => $discount,
                    'net' => $net,
                    'promotion_id' => $promotionId,
                    'promotion_snapshot' => $promoSnapshot,
                ];
            }

            // Whole-invoice promotion applies to the net subtotal.
            $subtotal = round(array_sum($lineNets), 2);
            $invoiceDiscount = round($manualInvoiceDiscount, 2);
            if ($invoicePromotionId) {
                $promo = Promotion::find($invoicePromotionId);
                if ($promo && $promo->isValidOn() && $subtotal >= (float) ($promo->min_spend ?? 0)) {
                    $invoiceDiscount = round($invoiceDiscount + $promo->discountFor($subtotal), 2);
                    $usedPromotions[$promo->id] = $promo;
                }
            }

            $calc = InvoiceCalculator::compute($lineNets, $invoiceDiscount, $taxEnabled, $taxRate, $taxInclusive);

            $invoice = Invoice::create([
                'patient_id' => $patient?->id,
                'invoice_no' => DocumentNumber::next(Invoice::query(), 'INV', 'invoice_no'),
                'status' => $status,
                'subtotal' => $calc['subtotal'],
                'discount_total' => $calc['discount_total'],
                'tax_total' => $calc['tax_total'],
                'grand_total' => $calc['grand_total'],
                'amount_paid' => 0,
                'tax_enabled' => $taxEnabled,
                'tax_rate_snapshot' => $taxRate,
                'tax_inclusive' => $taxInclusive,
                'issued_at' => $issuedAt ?? now(),
                'created_by' => $createdBy,
                'notes' => $notes,
            ]);

            foreach ($prepared as $i => $p) {
                $item = new InvoiceItem([
                    'invoice_id' => $invoice->id,
                    'description_snapshot' => $p['description'],
                    'quantity' => $p['quantity'],
                    'unit_price' => $p['unit_price'],
                    'discount' => $p['discount'],
                    'tax' => $calc['line_tax'][$i] ?? 0,
                    'line_total' => $p['net'],
                    'promotion_id' => $p['promotion_id'],
                    'promotion_snapshot' => $p['promotion_snapshot'],
                ]);

                if ($p['itemable'] instanceof Model) {
                    $item->itemable()->associate($p['itemable']);
                }
                $item->save();
            }

            foreach ($usedPromotions as $promo) {
                $promo->increment('used_count');
            }

            return $invoice->fresh('items');
        });
    }
}
