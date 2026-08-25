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
    public function handle(
        ?Patient $patient,
        array $lineGroups,
        array $payments = [],
        ?string $invoicePromotionId = null,
        ?int $performedBy = null,
        ?string $notes = null,
        bool $generateReceipt = false,
    ): array {
        return DB::transaction(function () use (
            $patient, $lineGroups, $payments, $invoicePromotionId, $performedBy, $notes, $generateReceipt
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

            // Nothing chargeable (e.g. only package-draw sessions) → no invoice, no payment.
            if (empty($invoiceLines)) {
                return ['invoice' => null, 'receipt' => null, 'sessions' => $sessions];
            }

            // 4. One invoice with promotions + tax.
            $invoice = $this->createInvoice->handle(
                $patient,
                $invoiceLines,
                invoicePromotionId: $invoicePromotionId,
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

            return ['invoice' => $invoice->fresh('items'), 'receipt' => $receipt, 'sessions' => $sessions];
        });
    }
}
