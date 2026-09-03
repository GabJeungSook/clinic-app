<?php

namespace App\Http\Controllers;

use App\Actions\Billing\Checkout;
use App\Actions\Patients\CreatePatient;
use App\Enums\AppointmentStatus;
use App\Enums\CourseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\TreatmentCourse;
use App\Models\Unit;
use App\Support\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The single checkout / point-of-sale screen. Supersedes the old Record Visit
 * (POS) and manual "New invoice" pages: it performs services (consuming their
 * bill of materials + logging sessions), sells retail products (deducting
 * stock), bills manual lines, applies promotions, and takes split payments.
 */
class CheckoutController extends Controller
{
    public function create(Request $request): Response
    {
        $services = Service::query()->where('is_active', true)
            ->with([
                'category:id,name,sort_order',
                'consumables.item:id,name',
                'consumables.unit:id,abbreviation',
            ])
            ->get()
            ->sortBy([fn ($s) => $s->category?->sort_order ?? 999, fn ($s) => $s->name])
            ->values()
            ->map(fn (Service $s) => [
                'value' => $s->id,
                'label' => ($s->category ? "{$s->category->name} · " : '') . $s->name,
                // Per-session price so the checkout bills one session by default.
                'price' => round((float) $s->default_price / max(1, (int) $s->default_session_count), 2),
                'sessions' => (int) $s->default_session_count,
                'bom' => $s->consumables->map(fn ($c) => [
                    'inventory_item_id' => $c->inventory_item_id,
                    'item_name' => $c->item?->name,
                    'quantity' => (float) $c->quantity,
                    'unit_id' => $c->unit_id,
                ])->values(),
            ]);

        // Outstanding balances per patient, so the front desk can settle them right
        // at checkout (no separate Billing trip). Package invoices expose a
        // per-session price to pre-fill a sensible installment amount.
        $coursesByInvoice = TreatmentCourse::query()
            ->whereNotNull('invoice_id')
            ->get(['id', 'invoice_id'])
            ->keyBy('invoice_id');

        $outstanding = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid])
            ->whereNotNull('patient_id')
            ->with('items:id,invoice_id,description_snapshot,unit_price,itemable_id')
            ->latest('issued_at')
            ->get()
            ->groupBy('patient_id')
            ->map(fn ($invoices) => $invoices->map(function (Invoice $i) use ($coursesByInvoice) {
                $course = $coursesByInvoice->get($i->id);
                $courseItem = $course ? $i->items->firstWhere('itemable_id', $course->id) : null;

                return [
                    'invoice_id' => $i->id,
                    'invoice_no' => $i->invoice_no,
                    'label' => $i->items->first()?->description_snapshot ?? "Invoice {$i->invoice_no}",
                    'balance' => $i->amountDue(),
                    'per_session' => $courseItem ? round((float) $courseItem->unit_price, 2) : null,
                    'course_id' => $course?->id,
                ];
            })->values());

        return Inertia::render('Checkout/Create', [
            'outstanding' => $outstanding,
            'patients' => Patient::query()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'code'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => "{$p->full_name} ({$p->code})"]),
            'services' => $services,
            // Retail products sold over the counter — these deduct stock.
            'items' => InventoryItem::query()->where('is_active', true)->where('type', 'retail')->orderBy('name')
                ->with('baseUnit:id,abbreviation')
                ->get(['id', 'name', 'default_sell_price', 'base_unit_id', 'stock_on_hand_cache', 'reorder_level'])
                ->map(fn ($i) => [
                    'value' => $i->id,
                    'label' => $i->name,
                    'price' => (float) $i->default_sell_price,
                    'unit' => $i->baseUnit?->abbreviation,
                    'on_hand' => (float) $i->stock_on_hand_cache,
                    'reorder' => (float) $i->reorder_level,
                    'is_low' => $i->isLowStock(),
                ]),
            // All active items — usable as service consumables (not just retail).
            'consumableItems' => InventoryItem::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'base_unit_id', 'stock_on_hand_cache', 'reorder_level'])
                ->map(fn ($i) => [
                    'value' => $i->id,
                    'label' => $i->name,
                    'base_unit_id' => $i->base_unit_id,
                    'on_hand' => (float) $i->stock_on_hand_cache,
                    'is_low' => $i->isLowStock(),
                ]),
            'units' => Unit::query()->orderBy('name')->get(['id', 'abbreviation', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->abbreviation]),
            'courses' => TreatmentCourse::query()->where('status', CourseStatus::Active)
                ->with('invoice')
                ->get()
                ->map(fn (TreatmentCourse $c) => [
                    'value' => $c->id,
                    'patient_id' => $c->patient_id,
                    'service_id' => $c->service_id,
                    'label' => $c->name_snapshot,
                    'total' => $c->total_sessions,
                    'completed' => $c->sessions_completed,
                    'remaining' => $c->sessions_remaining,
                    // The whole package is one invoice — "paid" means it's settled.
                    'paid' => $c->invoice ? $c->invoice->amountDue() <= 0 : false,
                ])
                ->filter(fn ($c) => $c['remaining'] > 0)
                ->values(),
            'promotions' => Promotion::query()->where('is_active', true)->orderBy('name')->get()
                ->filter(fn (Promotion $p) => $p->isValidOn())
                ->map(fn (Promotion $p) => [
                    'value' => $p->id,
                    'label' => $p->name . ' (' . ($p->type->value === 'percent' ? $p->value . '% off' : 'less ' . number_format((float) $p->value, 2)) . ')',
                    'min_spend' => $p->min_spend !== null ? (float) $p->min_spend : 0,
                ])
                ->values(),
            'methods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()]),
            'tax' => [
                'enabled' => Settings::taxEnabled(),
                'rate' => Settings::taxRate(),
                'inclusive' => Settings::taxInclusive(),
            ],
            'currency' => Settings::get('billing.currency_symbol', '₱'),
            'preselectedPatient' => $request->query('patient'),
            'preselectedService' => $request->query('service'),
            'preselectedCourse' => $request->query('course'),
            // Bookings the patient came in for, keyed by patient, so selecting them
            // at checkout auto-loads the service(s): any Confirmed booking (they've
            // arrived — regardless of the booked date) plus today's Scheduled ones.
            'appointmentPrefills' => Appointment::query()
                ->whereNotNull('patient_id')
                ->where(fn ($q) => $q
                    ->where('status', AppointmentStatus::Confirmed)
                    ->orWhere(fn ($s) => $s->where('status', AppointmentStatus::Scheduled)->whereDate('scheduled_at', Carbon::today())))
                ->get(['patient_id', 'service_id', 'course_id', 'services'])
                ->groupBy('patient_id')
                ->map(fn ($appts) => $appts->flatMap(function (Appointment $a) {
                    $list = collect($a->services ?? []);
                    if ($list->isEmpty() && $a->service_id) {
                        $list = collect([['service_id' => $a->service_id, 'course_id' => $a->course_id]]);
                    }

                    return $list->map(fn ($s) => ['service_id' => $s['service_id'], 'course_id' => $s['course_id'] ?? null]);
                })->values()),
        ]);
    }

    /** Quick-register a patient without leaving the checkout. */
    public function storePatient(Request $request, CreatePatient $createPatient): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $patient = $createPatient->handle([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? '',
            'phone' => $data['phone'] ?? null,
        ]);

        return redirect()->route('checkout.create', ['patient' => $patient->id])->with('success', 'Patient added.');
    }

    public function store(Request $request, Checkout $checkout): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'string', 'exists:patients,id'],
            'invoice_promotion_id' => ['nullable', 'string', 'exists:promotions,id'],
            'invoice_discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'line_groups.services' => ['array'],
            'line_groups.services.*.service_id' => ['required', 'string', 'exists:services,id'],
            'line_groups.services.*.course_id' => ['nullable', 'string', 'exists:treatment_courses,id'],
            'line_groups.services.*.sessions' => ['nullable', 'integer', 'min:1', 'max:100'],
            'line_groups.services.*.price' => ['required', 'numeric', 'min:0'],
            'line_groups.services.*.discount' => ['nullable', 'numeric', 'min:0'],
            'line_groups.services.*.promotion_id' => ['nullable', 'string', 'exists:promotions,id'],
            'line_groups.services.*.notes' => ['nullable', 'string'],
            'line_groups.services.*.consumables' => ['array'],
            'line_groups.services.*.consumables.*.inventory_item_id' => ['required', 'string', 'exists:inventory_items,id'],
            'line_groups.services.*.consumables.*.quantity' => ['required', 'numeric', 'min:0'],
            'line_groups.services.*.consumables.*.unit_id' => ['nullable', 'string', 'exists:units,id'],

            'line_groups.retail' => ['array'],
            'line_groups.retail.*.inventory_item_id' => ['required', 'string', 'exists:inventory_items,id'],
            'line_groups.retail.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'line_groups.retail.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_groups.retail.*.discount' => ['nullable', 'numeric', 'min:0'],
            'line_groups.retail.*.promotion_id' => ['nullable', 'string', 'exists:promotions,id'],

            'line_groups.freebies' => ['array'],
            'line_groups.freebies.*.inventory_item_id' => ['required', 'string', 'exists:inventory_items,id'],
            'line_groups.freebies.*.quantity' => ['required', 'numeric', 'min:0.001'],

            'line_groups.manual' => ['array'],
            'line_groups.manual.*.description' => ['required', 'string', 'max:255'],
            'line_groups.manual.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'line_groups.manual.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_groups.manual.*.discount' => ['nullable', 'numeric', 'min:0'],
            'line_groups.manual.*.promotion_id' => ['nullable', 'string', 'exists:promotions,id'],

            'payments' => ['array'],
            'payments.*.method' => ['required_with:payments', 'string', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:150'],

            // Balances being settled now against the patient's existing invoices.
            'settlements' => ['array'],
            'settlements.*.invoice_id' => ['required', 'string', 'exists:invoices,id'],
            'settlements.*.amount' => ['required', 'numeric', 'min:0.01'],
            'settlements.*.discount' => ['nullable', 'numeric', 'min:0'],
            'settlements.*.method' => ['required', 'string', Rule::enum(PaymentMethod::class)],

            'generate_receipt' => ['boolean'],
        ]);

        $groups = $data['line_groups'] ?? [];
        $services = $groups['services'] ?? [];
        $retail = $groups['retail'] ?? [];
        $freebies = $groups['freebies'] ?? [];
        $manual = $groups['manual'] ?? [];

        if (empty($services) && empty($retail) && empty($freebies) && empty($manual) && empty($data['settlements'] ?? [])) {
            throw ValidationException::withMessages(['line_groups' => 'Add at least one item to the sale.']);
        }
        $patientId = $data['patient_id'] ?? null;

        // A performed service must be tied to a patient chart.
        if (! empty($services) && empty($patientId)) {
            throw ValidationException::withMessages(['patient_id' => 'Select a patient for a service line.']);
        }

        // Drop zero-quantity consumable rows (a removed BoM item).
        $services = array_map(function (array $line) {
            $line['consumables'] = array_values(array_filter(
                $line['consumables'] ?? [],
                fn ($c) => (float) $c['quantity'] > 0,
            ));

            return $line;
        }, $services);

        // Guard: a retail sale or freebie can never take more than is on hand.
        foreach (array_merge($retail, $freebies) as $line) {
            $item = InventoryItem::find($line['inventory_item_id']);
            if ($item && (float) $line['quantity'] > $item->stockOnHand()) {
                throw ValidationException::withMessages([
                    'line_groups' => "Not enough stock for {$item->name} (on hand: " . rtrim(rtrim(number_format($item->stockOnHand(), 3), '0'), '.') . ').',
                ]);
            }
        }

        // Validate settlements: each must belong to the selected patient and never
        // collect (payment + discount) more than the balance actually owed.
        $settlements = $data['settlements'] ?? [];
        if (! empty($settlements)) {
            if (empty($patientId)) {
                throw ValidationException::withMessages(['settlements' => 'Select a patient to settle a balance.']);
            }
            $invoices = Invoice::query()
                ->whereIn('id', array_column($settlements, 'invoice_id'))
                ->get()->keyBy('id');
            foreach ($settlements as $s) {
                $inv = $invoices->get($s['invoice_id']);
                if (! $inv || $inv->patient_id !== $patientId) {
                    throw ValidationException::withMessages(['settlements' => 'A balance does not belong to this patient.']);
                }
                $collect = round((float) $s['amount'] + (float) ($s['discount'] ?? 0), 2);
                if ($collect > round((float) $inv->amountDue(), 2) + 0.01) {
                    throw ValidationException::withMessages(['settlements' => "Amount exceeds the balance on {$inv->invoice_no}."]);
                }
            }
        }

        try {
            $result = $checkout->handle(
                $patientId ? Patient::find($patientId) : null,
                ['services' => $services, 'retail' => $retail, 'freebies' => $freebies, 'manual' => $manual],
                payments: $data['payments'] ?? [],
                invoicePromotionId: $data['invoice_promotion_id'] ?? null,
                invoiceDiscount: (float) ($data['invoice_discount'] ?? 0),
                performedBy: $request->user()?->id,
                notes: $data['notes'] ?? null,
                generateReceipt: (bool) ($data['generate_receipt'] ?? false),
                settlements: $settlements,
            );
        } catch (InsufficientStockException $e) {
            throw ValidationException::withMessages(['line_groups' => $e->getMessage()]);
        }

        // Checking a patient out fulfils their booking: complete today's
        // scheduled/confirmed appointment(s) that share any of the services sold.
        if ($patientId && ! empty($services)) {
            $serviceIds = array_values(array_filter(array_map(fn ($s) => $s['service_id'] ?? null, $services)));
            if (! empty($serviceIds)) {
                Appointment::query()
                    ->where('patient_id', $patientId)
                    ->where(fn ($q) => $q
                        ->where('status', AppointmentStatus::Confirmed)
                        ->orWhere(fn ($s) => $s->where('status', AppointmentStatus::Scheduled)->whereDate('scheduled_at', Carbon::today())))
                    ->get()
                    ->each(function (Appointment $appt) use ($serviceIds) {
                        $apptServiceIds = collect($appt->services ?? [])->pluck('service_id')
                            ->push($appt->service_id)->filter()->all();
                        if (array_intersect($serviceIds, $apptServiceIds)) {
                            $appt->update(['status' => AppointmentStatus::Completed]);
                        }
                    });
            }
        }

        if ($result['receipt']) {
            return redirect()->route('receipts.show', $result['receipt']->id)->with('success', 'Sale recorded.');
        }
        if ($result['invoice']) {
            return redirect()->route('invoices.show', $result['invoice']->id)->with('success', 'Sale recorded.');
        }

        // No charge (e.g. only a prepaid package session) — back to the chart.
        return $patientId
            ? redirect()->route('patients.show', $patientId)->with('success', 'Visit recorded.')
            : redirect()->route('checkout.create')->with('success', 'Recorded.');
    }
}
