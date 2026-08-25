<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SessionStatus;
use App\Models\Appointment;
use App\Models\Batch;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\TreatmentCourse;
use App\Models\TreatmentSession;
use App\Support\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /** Shared metadata used by the printable header of every report. */
    private function meta(): array
    {
        return [
            'clinic' => Settings::get('clinic.name'),
            'generated_at' => now()->format('F j, Y g:iA'),
            'currency' => Settings::get('billing.currency_symbol', '₱'),
        ];
    }

    public function revenue(Request $request): Response
    {
        // Cash-basis sales: actual money received (payments.paid_at), filterable
        // by preset period or a custom date range.
        $preset = (string) $request->query('preset', 'month');

        [$from, $to] = match ($preset) {
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'week' => [Carbon::today()->startOfWeek(), Carbon::today()->endOfWeek()],
            'year' => [Carbon::today()->startOfYear(), Carbon::today()->endOfYear()],
            'custom' => [
                ($request->date('from') ?? Carbon::today()->subDays(29))->startOfDay(),
                ($request->date('to') ?? Carbon::today())->endOfDay(),
            ],
            default => [Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()],
        };

        $payments = Payment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->get(['amount', 'paid_at', 'method']);

        // Daily buckets for shorter spans, monthly for anything longer.
        $daily = $from->diffInDays($to) <= 62;
        $buckets = [];
        $cursor = $from->copy();
        if ($daily) {
            while ($cursor->lte($to)) {
                $buckets[] = ['key' => $cursor->toDateString(), 'label' => $cursor->format('M j')];
                $cursor->addDay();
            }
            $grouped = $payments->groupBy(fn ($p) => $p->paid_at->toDateString());
        } else {
            $cursor = $from->copy()->startOfMonth();
            $endMonth = $to->copy()->startOfMonth();
            while ($cursor->lte($endMonth)) {
                $buckets[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('M Y')];
                $cursor->addMonthNoOverflow();
            }
            $grouped = $payments->groupBy(fn ($p) => $p->paid_at->format('Y-m'));
        }

        $series = collect($buckets)->map(fn ($b) => [
            'label' => $b['label'],
            'value' => (float) ($grouped[$b['key']] ?? collect())->sum('amount'),
        ]);

        $methodBreakdown = collect(PaymentMethod::cases())
            ->map(fn ($m) => ['label' => $m->label(), 'value' => (float) $payments->where('method', $m)->sum('amount')])
            ->filter(fn ($r) => $r['value'] != 0.0)
            ->values();

        $gross = (float) $payments->where('amount', '>', 0)->sum('amount');
        $refunds = (float) abs($payments->where('amount', '<', 0)->sum('amount'));

        return Inertia::render('Reports/Revenue', [
            'meta' => $this->meta(),
            'preset' => $preset,
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->format('F j, Y') . ' – ' . $to->format('F j, Y'),
            ],
            'presets' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'week', 'label' => 'This week'],
                ['value' => 'month', 'label' => 'This month'],
                ['value' => 'year', 'label' => 'This year'],
                ['value' => 'custom', 'label' => 'Custom range'],
            ],
            'series' => $series,
            'methodBreakdown' => $methodBreakdown,
            'totals' => [
                'gross' => $gross,
                'refunds' => $refunds,
                'net' => round($gross - $refunds, 2),
                'count' => $payments->where('amount', '>', 0)->count(),
            ],
        ]);
    }

    public function sales(Request $request): Response
    {
        // Invoice-based (accrual) sales with a full breakdown — every billed sale
        // in the period, its money split, and what was sold. Voided/draft excluded.
        $preset = (string) $request->query('preset', 'month');

        [$from, $to] = match ($preset) {
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'week' => [Carbon::today()->startOfWeek(), Carbon::today()->endOfWeek()],
            'year' => [Carbon::today()->startOfYear(), Carbon::today()->endOfYear()],
            'custom' => [
                ($request->date('from') ?? Carbon::today()->subDays(29))->startOfDay(),
                ($request->date('to') ?? Carbon::today())->endOfDay(),
            ],
            default => [Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()],
        };

        $invoices = Invoice::query()
            ->whereBetween('issued_at', [$from, $to])
            ->whereNotIn('status', [InvoiceStatus::Void, InvoiceStatus::Draft])
            ->with('patient:id,first_name,last_name', 'items', 'payments')
            ->orderByDesc('issued_at')
            ->get();

        $payments = $invoices->flatMap->payments;

        $byStatus = $invoices->groupBy(fn (Invoice $i) => $i->status->value)
            ->map(fn ($rows, $status) => [
                'label' => ucwords(str_replace('_', ' ', $status)),
                'count' => $rows->count(),
                'total' => (float) $rows->sum(fn ($i) => (float) $i->grand_total),
            ])
            ->sortByDesc('total')
            ->values();

        $byMethod = collect(PaymentMethod::cases())
            ->map(fn ($m) => ['label' => $m->label(), 'value' => (float) $payments->where('method', $m)->sum('amount')])
            ->filter(fn ($r) => $r['value'] != 0.0)
            ->values();

        $itemsSold = $invoices->flatMap->items
            ->groupBy('description_snapshot')
            ->map(fn ($rows, $name) => [
                'label' => (string) $name,
                'qty' => (float) $rows->sum(fn ($i) => (float) $i->quantity),
                'total' => (float) $rows->sum(fn ($i) => (float) $i->line_total),
            ])
            ->sortByDesc('total')
            ->values();

        return Inertia::render('Reports/Sales', [
            'meta' => $this->meta(),
            'preset' => $preset,
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $from->format('F j, Y') . ' – ' . $to->format('F j, Y'),
            ],
            'presets' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => 'week', 'label' => 'This week'],
                ['value' => 'month', 'label' => 'This month'],
                ['value' => 'year', 'label' => 'This year'],
                ['value' => 'custom', 'label' => 'Custom range'],
            ],
            'totals' => [
                'count' => $invoices->count(),
                'subtotal' => (float) $invoices->sum(fn ($i) => (float) $i->subtotal),
                'discount' => (float) $invoices->sum(fn ($i) => (float) $i->discount_total),
                'tax' => (float) $invoices->sum(fn ($i) => (float) $i->tax_total),
                'grand' => (float) $invoices->sum(fn ($i) => (float) $i->grand_total),
                'collected' => (float) $invoices->sum(fn ($i) => (float) $i->amount_paid),
                'outstanding' => (float) $invoices->sum(fn ($i) => $i->amountDue()),
            ],
            'byStatus' => $byStatus,
            'byMethod' => $byMethod,
            'itemsSold' => $itemsSold,
            'ledger' => $invoices->map(fn (Invoice $i) => [
                'issued_at' => $i->issued_at?->format('F j, Y g:iA'),
                'invoice_no' => $i->invoice_no,
                'patient' => $i->patient?->full_name,
                'status' => ucwords(str_replace('_', ' ', $i->status->value)),
                'items' => $i->items->count(),
                'subtotal' => (float) $i->subtotal,
                'discount' => (float) $i->discount_total,
                'tax' => (float) $i->tax_total,
                'grand' => (float) $i->grand_total,
                'paid' => (float) $i->amount_paid,
                'due' => $i->amountDue(),
                'methods' => $i->payments->where('amount', '>', 0)->map(fn ($p) => $p->method->label())->unique()->values()->all(),
            ]),
        ]);
    }

    public function inventory(Request $request): Response
    {
        $threshold = (int) Settings::get('inventory.expiry_threshold_days', 30);

        // Date range for the ins/outs (stock movement) section — default 30 days.
        $from = ($request->date('from') ?? Carbon::today()->subDays(29))->startOfDay();
        $to = ($request->date('to') ?? Carbon::today())->endOfDay();

        $moves = StockMovement::query()
            ->with('item:id,name', 'performer:id,name')
            ->whereBetween('occurred_at', [$from, $to])
            ->orderByDesc('occurred_at')
            ->get();

        // Per-item in / out / net over the period.
        $itemSummary = $moves->groupBy('inventory_item_id')
            ->map(function ($rows) {
                $in = (float) $rows->where('quantity', '>', 0)->sum('quantity');
                $out = (float) abs($rows->where('quantity', '<', 0)->sum('quantity'));

                return [
                    'item' => $rows->first()->item?->name,
                    'in' => $in,
                    'out' => $out,
                    'net' => $in - $out,
                ];
            })
            ->sortByDesc('out')
            ->values();

        // Detailed chronological ledger (ins and outs).
        $ledger = $moves->take(300)->map(fn (StockMovement $m) => [
            'item' => $m->item?->name,
            'type' => $m->type->label(),
            'direction' => $m->type->isInflow() ? 'in' : 'out',
            'quantity' => (float) $m->quantity,
            'occurred_at' => $m->occurred_at?->format('F j, Y g:iA'),
            'by' => $m->performer?->name,
            'reason' => $m->reason,
        ]);

        $stockValue = (float) Batch::query()
            ->get(['qty_remaining_cache', 'unit_cost'])
            ->sum(fn ($b) => (float) $b->qty_remaining_cache * (float) $b->unit_cost);

        return Inertia::render('Reports/Inventory', [
            'meta' => $this->meta(),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $from->format('F j, Y') . ' – ' . $to->format('F j, Y')],
            'stockValue' => $stockValue,
            'movementTotals' => [
                'in_count' => $moves->where('quantity', '>', 0)->count(),
                'out_count' => $moves->where('quantity', '<', 0)->count(),
            ],
            'itemSummary' => $itemSummary,
            'ledger' => $ledger,
            'lowStock' => InventoryItem::query()
                ->where('reorder_level', '>', 0)
                ->whereColumn('stock_on_hand_cache', '<=', 'reorder_level')
                ->with('baseUnit:id,abbreviation')
                ->orderBy('stock_on_hand_cache')
                ->get(['id', 'name', 'stock_on_hand_cache', 'reorder_level', 'base_unit_id'])
                ->map(fn ($i) => ['name' => $i->name, 'on_hand' => (float) $i->stock_on_hand_cache, 'reorder' => (float) $i->reorder_level, 'unit' => $i->baseUnit?->abbreviation]),
            'expiring' => Batch::query()
                ->whereNotNull('expiry_date')
                ->where('qty_remaining_cache', '>', 0)
                ->whereDate('expiry_date', '<=', Carbon::today()->addDays($threshold))
                ->with('item:id,name')
                ->orderBy('expiry_date')
                ->get()
                ->map(fn ($b) => [
                    'item' => $b->item?->name,
                    'batch' => $b->batch_number,
                    'expiry' => $b->expiry_date?->format('F j, Y'),
                    'qty' => (float) $b->qty_remaining_cache,
                    'expired' => $b->isExpired(),
                ]),
        ]);
    }

    public function purchasing(Request $request): Response
    {
        // Received purchases (actual spend) within a date range.
        $from = ($request->date('from') ?? Carbon::today()->subDays(29))->startOfDay();
        $to = ($request->date('to') ?? Carbon::today())->endOfDay();

        $purchases = Purchase::query()
            ->where('status', PurchaseStatus::Received)
            ->whereBetween('received_at', [$from, $to])
            ->with('supplier:id,name', 'items:id,purchase_id,inventory_item_id,quantity,unit_cost', 'items.item:id,name')
            ->orderByDesc('received_at')
            ->get();

        $lines = $purchases->flatMap(fn (Purchase $p) => $p->items);

        $bySupplier = $purchases->groupBy(fn (Purchase $p) => $p->supplier?->name ?? 'No supplier')
            ->map(fn ($rows, $name) => [
                'label' => $name,
                'total' => (float) $rows->sum(fn ($p) => (float) $p->total_cost),
                'count' => $rows->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $topItems = $lines->groupBy(fn ($i) => $i->item?->name ?? '—')
            ->map(fn ($rows, $name) => [
                'label' => $name,
                'spend' => (float) $rows->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost),
                'qty' => (float) $rows->sum(fn ($i) => (float) $i->quantity),
            ])
            ->sortByDesc('spend')
            ->take(10)
            ->values();

        return Inertia::render('Reports/Purchasing', [
            'meta' => $this->meta(),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $from->format('F j, Y') . ' – ' . $to->format('F j, Y')],
            'totals' => [
                'spend' => (float) $purchases->sum(fn ($p) => (float) $p->total_cost),
                'count' => $purchases->count(),
                'suppliers' => $bySupplier->count(),
                'lines' => $lines->count(),
            ],
            'bySupplier' => $bySupplier,
            'topItems' => $topItems,
            'ledger' => $purchases->map(fn (Purchase $p) => [
                'reference' => $p->reference_no,
                'supplier' => $p->supplier?->name,
                'received_at' => $p->received_at?->format('F j, Y'),
                'items' => $p->items->count(),
                'total' => (float) $p->total_cost,
            ]),
        ]);
    }

    public function appointments(): Response
    {
        $start = Carbon::today()->subDays(29);

        $appts = Appointment::query()
            ->where('scheduled_at', '>=', $start)
            ->with('service:id,name')
            ->get(['id', 'service_id', 'status', 'scheduled_at']);

        $byDay = $appts->groupBy(fn ($a) => $a->scheduled_at->toDateString())->map->count();

        $series = collect(range(0, 29))->map(function ($i) use ($start, $byDay) {
            $date = $start->copy()->addDays($i);

            return ['label' => $date->format('M j'), 'value' => (int) ($byDay[$date->toDateString()] ?? 0)];
        });

        // Status mix over the period.
        $statusCounts = collect(AppointmentStatus::cases())->map(fn ($s) => [
            'label' => $s->label(),
            'value' => (int) $appts->where('status', $s)->count(),
        ]);

        $completed = $appts->where('status', AppointmentStatus::Completed)->count();
        $noShow = $appts->where('status', AppointmentStatus::NoShow)->count();
        $resolved = $completed + $noShow;

        $topServices = $appts->groupBy(fn ($a) => $a->service?->name ?? 'Unassigned')
            ->map(fn ($rows, $name) => ['label' => $name, 'count' => $rows->count()])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        return Inertia::render('Reports/Appointments', [
            'meta' => $this->meta(),
            'series' => $series,
            'statusCounts' => $statusCounts,
            'topServices' => $topServices,
            'totals' => [
                'total' => $appts->count(),
                'completed' => $completed,
                'no_show' => $noShow,
                'no_show_rate' => $resolved > 0 ? round($noShow / $resolved * 100, 1) : 0.0,
            ],
        ]);
    }

    public function patients(): Response
    {
        $start = Carbon::today()->subDays(29);

        $byDay = Patient::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($p) => $p->created_at->toDateString())
            ->map->count();

        $series = collect(range(0, 29))->map(function ($i) use ($start, $byDay) {
            $date = $start->copy()->addDays($i);

            return ['label' => $date->format('M j'), 'value' => (int) ($byDay[$date->toDateString()] ?? 0)];
        });

        // Top patients by lifetime amount invoiced.
        $topPatients = Invoice::query()
            ->selectRaw('patient_id, COUNT(*) as invoices, SUM(grand_total) as total')
            ->whereNotNull('patient_id')
            ->groupBy('patient_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('patient:id,first_name,last_name')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->patient?->full_name ?? 'Unknown',
                'invoices' => (int) $r->invoices,
                'total' => (float) $r->total,
            ]);

        $demographics = Patient::query()
            ->get(['sex'])
            ->groupBy(fn ($p) => $p->sex?->value)
            ->map->count();

        return Inertia::render('Reports/Patients', [
            'meta' => $this->meta(),
            'series' => $series,
            'topPatients' => $topPatients,
            'demographics' => [
                'male' => (int) ($demographics['male'] ?? 0),
                'female' => (int) ($demographics['female'] ?? 0),
                'other' => (int) ($demographics['other'] ?? 0),
            ],
            'totals' => [
                'total' => Patient::query()->count(),
                'new_month' => Patient::query()->where('created_at', '>=', Carbon::today()->startOfMonth())->count(),
                'new_30' => (int) $series->sum('value'),
            ],
        ]);
    }

    public function treatments(): Response
    {
        $start = Carbon::today()->subDays(29);

        $byDay = TreatmentSession::query()
            ->where('status', SessionStatus::Completed)
            ->where('performed_at', '>=', $start)
            ->get(['performed_at'])
            ->groupBy(fn ($s) => $s->performed_at->toDateString())
            ->map(fn ($rows) => $rows->count());

        $series = collect(range(0, 29))->map(function ($i) use ($start, $byDay) {
            $date = $start->copy()->addDays($i);

            return ['label' => $date->format('M j'), 'value' => (int) ($byDay[$date->toDateString()] ?? 0)];
        });

        $topServices = TreatmentCourse::query()
            ->selectRaw('name_snapshot, COUNT(*) as count')
            ->groupBy('name_snapshot')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['label' => $r->name_snapshot, 'count' => (int) $r->count]);

        return Inertia::render('Reports/Treatments', [
            'meta' => $this->meta(),
            'series' => $series,
            'topServices' => $topServices,
            'totals' => [
                'sessions_30' => (int) $series->sum('value'),
                'active_courses' => TreatmentCourse::query()->where('status', 'active')->count(),
                'completed_courses' => TreatmentCourse::query()->where('status', 'completed')->count(),
            ],
        ]);
    }
}
