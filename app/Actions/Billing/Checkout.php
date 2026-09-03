<?php

namespace App\Actions\Billing;

use App\Actions\Inventory\ConsumeStockFefo;
use App\Actions\Treatments\CompleteTreatmentSession;
use App\Actions\Treatments\PurchaseTreatmentCourse;
use App\Actions\Treatments\StartTreatmentSession;
use App\Enums\InvoiceStatus;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\SessionStatus;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\Service;
use App\Models\TreatmentCourse;
use Illuminate\Support\Facades\DB;

/**
 * The single point-of-sale / checkout orchestrator. Handles every kind of sale
 * in one transaction:
 *   - service lines performed now (create + complete a treatment session, which
 *     consumes the service bill-of-materials as SessionConsume; auto-create a
 *     package for multi-session services or draw from an existing one),
 *   - retail lines (bill the product AND deduct stock as SaleOut),
 *   - manual free-text lines,
 * then build one invoice (with line/invoice promotions + tax via CreateInvoice)
 * and record any number of split payments before optionally issuing a receipt.
 *
 * Consumption guardrails: service BoM is consumed exactly once (step 1), retail
 * stock exactly once (step 5), and the invoice is created exactly once (step 4).
 */
class Checkout
{
    public function __construct(
        private readonly PurchaseTreatmentCourse $purchaseCourse,
        private readonly StartTreatmentSession $start,
        private readonly CompleteTreatmentSession $complete,
        private readonly ConsumeStockFefo $consume,
        private readonly CreateInvoice $createInvoice,
        private readonly RecordPayment $recordPayment,
        private readonly GenerateReceipt $generateReceipt,
    ) {}

    /**
     * @param  array{
     *     services?: array<int, array{service_id:string, course_id?:?string, price:float,
     *         notes?:?string, discount?:float, promotion_id?:?string,
     *         consumables?: array<int, array{inventory_item_id:string, quantity:float, unit_id?:?string}>}>,
     *     retail?: array<int, array{inventory_item_id:string, quantity:float,
     *         unit_price:float, discount?:float, promotion_id?:?string}>,
     *     manual?: array<int, array{description:string, quantity:float, unit_price:float,
     *         discount?:float, promotion_id?:?string}>
     * }  $lineGroups
     * @param  array<int, array{method:string, amount:float, reference?:?string}>  $payments
     * @return array{invoice: ?Invoice, receipt: ?Receipt, sessions: array<int, \App\Models\TreatmentSession>}
     */
    /**
     * @param  array<int, array{invoice_id:string, amount:float, discount?:float, method?:string, reference?:?string}>  $settlements
     *         Payments collected now against the patient's EXISTING unpaid invoices
     *         (e.g. paying off a prepaid package balance during a session visit).
     */
    public function handle(
        ?Patient $patient,
        array $lineGroups,
        array $payments = [],
        ?string $invoicePromotionId = null,
        float $invoiceDiscount = 0,
        ?int $performedBy = null,
        ?string $notes = null,
        bool $generateReceipt = false,
        array $settlements = [],
    ): array {
        return DB::transaction(function () use (
            $patient, $lineGroups, $payments, $invoicePromotionId, $invoiceDiscount, $performedBy, $notes, $generateReceipt, $settlements
        ) {
            $sessions = [];
            $invoiceLines = [];
            $newCourses = [];
            $retailToConsume = [];   // [ [InventoryItem, qtyBase], … ] — consumed AFTER the invoice

            // 1. Service lines — perform one session now (consuming its BoM once),
            //    billing per session. Prepaying more than one session creates a
            //    package (course) for the remainder; drawing from an existing
            //    package charges nothing.
            foreach ($lineGroups['services'] ?? [] as $line) {
                $service = Service::findOrFail($line['service_id']);
                $course = ! empty($line['course_id']) ? TreatmentCourse::find($line['course_id']) : null;
                $perSession = round((float) $line['price'], 2);
                $sessionCount = max(1, (int) round((float) ($line['sessions'] ?? 1)));
                $createdCourse = false;

                // Prepaying 2+ sessions → open a package for them.
                if ($course === null && $sessionCount > 1) {
                    $course = $this->purchaseCourse->handle(
                        $patient,
                        $service,
                        totalSessions: $sessionCount,
                        price: round($perSession * $sessionCount, 2),
                        purchasedAt: now(),
                    );
                    $createdCourse = true;
                    $newCourses[] = $course;
                }

                $session = $this->start->handle(
                    $patient,
                    $service,
                    $course,
                    status: SessionStatus::Scheduled,
                    performedBy: $performedBy,
                    clinicalNotes: $line['notes'] ?? null,
                );

                // Front desk's consumables list is authoritative (empty = consume nothing).
                $this->complete->handle(
                    $session,
                    performedBy: $performedBy,
                    consumptionOverrides: array_values($line['consumables'] ?? []),
                    allowOverride: true,
                    performedAt: now(),
                );

                $sessions[] = $session;

                if ($perSession > 0) {
                    $invoiceLines[] = [
                        'description' => $createdCourse
                            ? "{$service->name} (package of {$sessionCount})"
                            : $service->name,
                        'quantity' => $sessionCount,
                        'unit_price' => $perSession,
                        'discount' => round((float) ($line['discount'] ?? 0), 2),
                        'promotion_id' => $line['promotion_id'] ?? null,
                        'itemable' => $course ?? $service,
                    ];
                }
            }

            // 2. Retail lines — bill now, deduct stock after the invoice exists.
            foreach ($lineGroups['retail'] ?? [] as $line) {
                $item = InventoryItem::findOrFail($line['inventory_item_id']);
                $qty = round((float) $line['quantity'], 3);

                $invoiceLines[] = [
                    'description' => $item->name,
                    'quantity' => $qty,
                    'unit_price' => round((float) $line['unit_price'], 2),
                    'discount' => round((float) ($line['discount'] ?? 0), 2),
                    'promotion_id' => $line['promotion_id'] ?? null,
                    'itemable' => $item,
                ];

                $retailToConsume[] = [$item, $qty];
            }

            // 2b. Freebies — given free (₱0) but still deducted from stock so the
            //     inventory stays accurate.
            foreach ($lineGroups['freebies'] ?? [] as $line) {
                $item = InventoryItem::findOrFail($line['inventory_item_id']);
                $qty = round((float) $line['quantity'], 3);

                $invoiceLines[] = [
                    'description' => $item->name . ' (Freebie)',
                    'quantity' => $qty,
                    'unit_price' => 0,
                    'discount' => 0,
                    'promotion_id' => null,
                    'itemable' => $item,
                ];

                $retailToConsume[] = [$item, $qty];
            }

            // 3. Manual free-text lines.
            foreach ($lineGroups['manual'] ?? [] as $line) {
                $invoiceLines[] = [
                    'description' => (string) $line['description'],
                    'quantity' => round((float) $line['quantity'], 3),
                    'unit_price' => round((float) $line['unit_price'], 2),
                    'discount' => round((float) ($line['discount'] ?? 0), 2),
                    'promotion_id' => $line['promotion_id'] ?? null,
                    'itemable' => null,
                ];
            }

            // 4–8 only run when there's something new to bill this visit. A visit
            // that only draws prepaid sessions has no new invoice, but may still
            // settle old balances (step 9), so we don't return early here.
            $invoice = null;
            $receipt = null;
            if (! empty($invoiceLines)) {
                // 4. One invoice with promotions + tax.
                $invoice = $this->createInvoice->handle(
                    $patient,
                    $invoiceLines,
                    invoicePromotionId: $invoicePromotionId,
                    manualInvoiceDiscount: $invoiceDiscount,
                    status: InvoiceStatus::Unpaid,
                    createdBy: $performedBy,
                    notes: $notes,
                );

                // 5. Deduct retail stock (SaleOut), tracing each movement to the sale.
                foreach ($retailToConsume as [$item, $qty]) {
                    $this->consume->handle(
                        $item,
                        $qty,
                        reference: $invoice,
                        performedBy: $performedBy,
                        type: MovementType::SaleOut,
                    );
                }

                // 6. Link newly created packages to the invoice that paid for them.
                foreach ($newCourses as $course) {
                    $course->forceFill(['invoice_id' => $invoice->id])->save();
                }

                // 7. Split payments — status auto-derives (Unpaid / PartiallyPaid / Paid).
                foreach ($payments as $payment) {
                    $amount = round((float) $payment['amount'], 2);
                    if ($amount === 0.0) {
                        continue;
                    }
                    $this->recordPayment->handle(
                        $invoice,
                        $amount,
                        PaymentMethod::from($payment['method']),
                        reference: $payment['reference'] ?? null,
                        receivedBy: $performedBy,
                    );
                }

                // 8. Optional receipt.
                $receipt = $generateReceipt ? $this->generateReceipt->handle($invoice->fresh()) : null;
            }

            // 9. Settle outstanding balances on the patient's EXISTING invoices —
            //    e.g. collecting a per-session installment on a prepaid package while
            //    the patient is in for that session. An optional discount writes off
            //    part of the balance; the payment covers the rest.
            $settled = [];
            foreach ($settlements as $s) {
                $existing = Invoice::find($s['invoice_id']);
                if (! $existing) {
                    continue;
                }

                $discount = round((float) ($s['discount'] ?? 0), 2);
                $amount = round((float) ($s['amount'] ?? 0), 2);

                if ($discount > 0) {
                    $existing->forceFill([
                        'discount_total' => round((float) $existing->discount_total + $discount, 2),
                        'grand_total' => max(0, round((float) $existing->grand_total - $discount, 2)),
                    ])->save();
                }

                if ($amount > 0) {
                    // RecordPayment re-derives the invoice status against the (now
                    // possibly discounted) grand total.
                    $this->recordPayment->handle(
                        $existing,
                        $amount,
                        PaymentMethod::from($s['method'] ?? 'cash'),
                        reference: $s['reference'] ?? null,
                        receivedBy: $performedBy,
                    );
                }

                $settled[] = $existing->fresh();
            }

            return [
                'invoice' => $invoice?->fresh('items'),
                'receipt' => $receipt,
                'sessions' => $sessions,
                'settled' => $settled,
            ];
        });
    }
}
