<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\CourseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SessionStatus;
use App\Models\Appointment;
use App\Models\Batch;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TreatmentCourse;
use App\Models\TreatmentSession;
use App\Support\Settings\Settings;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = Carbon::today();
        $threshold = (int) Settings::get('inventory.expiry_threshold_days', 30);

        $lowStock = InventoryItem::query()
            ->where('is_active', true)
            ->where('reorder_level', '>', 0)
            ->whereColumn('stock_on_hand_cache', '<=', 'reorder_level')
            ->with('baseUnit:id,abbreviation')
            ->orderBy('stock_on_hand_cache')
            ->limit(8)
            ->get(['id', 'name', 'base_unit_id', 'stock_on_hand_cache', 'reorder_level'])
            ->map(fn (InventoryItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->baseUnit?->abbreviation,
                'stock_on_hand_cache' => (float) $i->stock_on_hand_cache,
                'reorder_level' => (float) $i->reorder_level,
            ]);

        // Oversold = negative on hand: a sale/consumption drove stock below zero,
        // usually a wrong recipe or an unrecorded delivery. Surfaced so it gets
        // reconciled with a stock count the same day.
        $oversold = InventoryItem::query()
            ->where('is_active', true)
            ->where('stock_on_hand_cache', '<', 0)
            ->with('baseUnit:id,abbreviation')
            ->orderBy('stock_on_hand_cache')
            ->limit(8)
            ->get(['id', 'name', 'base_unit_id', 'stock_on_hand_cache'])
            ->map(fn (InventoryItem $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'unit' => $i->baseUnit?->abbreviation,
                'on_hand' => (float) $i->stock_on_hand_cache,
            ]);

        $expiringSoon = Batch::query()
            ->whereNotNull('expiry_date')
            ->where('qty_remaining_cache', '>', 0)
            ->whereDate('expiry_date', '<=', $today->copy()->addDays($threshold))
            ->with('item:id,name')
            ->orderBy('expiry_date')
            ->limit(8)
            ->get(['id', 'inventory_item_id', 'batch_number', 'expiry_date', 'qty_remaining_cache']);

        // 14-day trend series for the dashboard charts.
        $start = $today->copy()->subDays(13);

        $paymentsByDay = Payment::query()
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at'])
            ->groupBy(fn ($p) => $p->paid_at->toDateString())
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $sessionsByDay = TreatmentSession::query()
            ->where('status', SessionStatus::Completed)
            ->where('performed_at', '>=', $start)
            ->get(['performed_at'])
            ->groupBy(fn ($s) => $s->performed_at->toDateString())
            ->map(fn ($rows) => $rows->count());

        $revenueSeries = [];
        $sessionsSeries = [];
        foreach (range(0, 13) as $i) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $short = $date->format('M j');
            $revenueSeries[] = ['label' => $short, 'value' => (float) ($paymentsByDay[$key] ?? 0)];
            $sessionsSeries[] = ['label' => $short, 'value' => (int) ($sessionsByDay[$key] ?? 0)];
        }

        $appointmentsToday = Appointment::query()
            ->whereBetween('scheduled_at', [$today, $today->copy()->endOfDay()])
            ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
            ->with('patient:id,first_name,last_name', 'service:id,name')
            ->orderBy('scheduled_at')
            ->limit(8)
            ->get()
            ->map(fn (Appointment $a) => [
                'id' => $a->id,
                'name' => $a->displayName(),
                'service' => $a->service?->name,
                'time' => $a->scheduled_at?->format('g:iA'),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'revenue_today' => (float) Payment::query()->whereDate('paid_at', $today)->sum('amount'),
                'revenue_month' => (float) Payment::query()
                    ->whereBetween('paid_at', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                    ->sum('amount'),
                'patients' => Patient::query()->count(),
                'active_courses' => TreatmentCourse::query()->where('status', CourseStatus::Active)->count(),
                'sessions_today' => TreatmentSession::query()
                    ->where('status', SessionStatus::Completed)
                    ->whereDate('performed_at', $today)
                    ->count(),
                'open_invoices' => Invoice::query()
                    ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid])
                    ->count(),
                'outstanding_amount' => (float) Invoice::query()
                    ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::PartiallyPaid])
                    ->get()
                    ->sum(fn ($i) => $i->amountDue()),
                'low_stock_count' => $lowStock->count(),
                'expiring_soon_count' => $expiringSoon->count(),
                'oversold_count' => $oversold->count(),
                'appointments_today' => Appointment::query()
                    ->whereBetween('scheduled_at', [$today, $today->copy()->endOfDay()])
                    ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed])
                    ->count(),
            ],
            'lowStock' => $lowStock,
            'oversold' => $oversold,
            'expiringSoon' => $expiringSoon,
            'appointmentsToday' => $appointmentsToday,
            'revenueSeries' => $revenueSeries,
            'sessionsSeries' => $sessionsSeries,
            'currency' => Settings::get('billing.currency_symbol', '₱'),
        ]);
    }
}
