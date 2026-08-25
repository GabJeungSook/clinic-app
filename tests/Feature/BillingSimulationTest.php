<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Support\Branches\CurrentBranch;
use Database\Seeders\ClinicDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * End-to-end "day at the clinic" simulation: drives the real checkout / payment
 * endpoints, then proves (a) each invoice settles to the correct status + balance,
 * (b) retail stock is deducted, and (c) the revenue report reconciles exactly to
 * the payments ledger (cash basis, incl. a refund).
 */
class BillingSimulationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentBranch::flush();
        $this->seed(DatabaseSeeder::class);
        $this->seed(ClinicDemoSeeder::class);
        $this->owner = User::query()->where('email', 'owner@clinic.test')->firstOrFail();
        $this->actingAs($this->owner);

        // Deterministic totals: turn tax off for the simulation.
        $this->put('/clinic-settings', [
            'clinic_name' => 'Skinthera',
            'currency' => 'PHP',
            'currency_symbol' => '₱',
            'tax_enabled' => false,
            'tax_rate' => 0,
            'expiry_threshold_days' => 30,
        ])->assertRedirect();
    }

    public function test_a_full_day_of_billing_reconciles_to_the_revenue_report(): void
    {
        $patient = Patient::query()->firstOrFail();

        // Baseline "today" ledger (the demo seeder books some payments dated today);
        // my sales are measured as a delta on top of this.
        $base = $this->ledgerToday();

        // ---- Sale 1: single-session service, ₱1,500 settled by a split payment ----
        $service = Service::query()->where('default_session_count', 1)->firstOrFail();
        $this->post('/checkout', [
            'patient_id' => $patient->id,
            'line_groups' => ['services' => [['service_id' => $service->id, 'price' => 1500]]],
            'payments' => [
                ['method' => 'cash', 'amount' => 1000],
                ['method' => 'ewallet', 'amount' => 500],
            ],
        ])->assertRedirect();

        $sale1 = Invoice::query()->orderByDesc('id')->firstOrFail();
        $this->assertEqualsWithDelta(1500.0, (float) $sale1->grand_total, 0.001, 'Sale 1 total');
        $this->assertSame('paid', $sale1->status->value, 'Split payment should fully settle sale 1');
        $this->assertEqualsWithDelta(0.0, $sale1->amountDue(), 0.001, 'Sale 1 balance');

        // ---- Sale 2: retail — 2 units @ ₱150, must deduct stock ----
        $item = InventoryItem::query()->where('stock_on_hand_cache', '>', 5)->firstOrFail();
        $stockBefore = (float) $item->fresh()->stock_on_hand_cache;
        $this->post('/checkout', [
            'line_groups' => ['retail' => [[
                'inventory_item_id' => $item->id,
                'quantity' => 2,
                'unit_price' => 150,
            ]]],
            'payments' => [['method' => 'cash', 'amount' => 300]],
        ])->assertRedirect();

        $this->assertEqualsWithDelta($stockBefore - 2, (float) $item->fresh()->stock_on_hand_cache, 0.001, 'Retail sale must deduct stock');

        // ---- Sale 3: manual ₱1,000, only ₱600 paid → partially paid, ₱400 due ----
        $this->post('/checkout', [
            'patient_id' => $patient->id,
            'line_groups' => ['manual' => [['description' => 'Product bundle', 'quantity' => 1, 'unit_price' => 1000]]],
            'payments' => [['method' => 'cash', 'amount' => 600]],
        ])->assertRedirect();

        $sale3 = Invoice::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('partially_paid', $sale3->status->value, 'Under-payment should be partially paid');
        $this->assertEqualsWithDelta(400.0, $sale3->amountDue(), 0.001, 'Sale 3 balance');

        // ---- Refund ₱200 against sale 1 (negative payment via the invoice endpoint) ----
        $this->post("/invoices/{$sale1->id}/payments", ['amount' => -200, 'method' => 'cash'])
            ->assertRedirect();
        $sale1->refresh();
        $this->assertEqualsWithDelta(1300.0, (float) $sale1->amount_paid, 0.001, 'Refund reduces amount paid');
        $this->assertSame('partially_paid', $sale1->status->value, 'Refund reopens a fully-paid invoice');

        // ---- My four sales should have moved the ledger by exactly these deltas ----
        $now = $this->ledgerToday();
        $this->assertEqualsWithDelta(2400.0, $now['gross'] - $base['gross'], 0.001, 'Gross added by my sales');
        $this->assertEqualsWithDelta(200.0, $now['refunds'] - $base['refunds'], 0.001, 'Refunds added by my sales');
        $this->assertEqualsWithDelta(2200.0, $now['net'] - $base['net'], 0.001, 'Net added by my sales');
        $this->assertSame(4, $now['count'] - $base['count'], 'Positive payments added by my sales');
        $this->assertEqualsWithDelta(1700.0, $now['cash'] - $base['cash'], 0.001, 'Cash added by my sales');
        $this->assertEqualsWithDelta(500.0, $now['ewallet'] - $base['ewallet'], 0.001, 'E-wallet added by my sales');

        // ---- The revenue report must reconcile EXACTLY to the full ledger ----
        $this->get('/reports/revenue?preset=today')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Revenue')
                ->where('totals.gross', fn ($v) => abs((float) $v - $now['gross']) < 0.001)
                ->where('totals.refunds', fn ($v) => abs((float) $v - $now['refunds']) < 0.001)
                ->where('totals.net', fn ($v) => abs((float) $v - $now['net']) < 0.001)
                ->where('totals.count', fn ($v) => (int) $v === $now['count'])
                ->where('methodBreakdown', fn ($rows) => $this->methodMatches($rows, [
                    'Cash' => $now['cash'],
                    'E-wallet' => $now['ewallet'],
                ]))
            );
    }

    public function test_voiding_an_unpaid_invoice_restocks_and_drops_it_from_sales(): void
    {
        $item = InventoryItem::query()->where('stock_on_hand_cache', '>', 5)->firstOrFail();
        $stockBefore = (float) $item->fresh()->stock_on_hand_cache;

        // A retail sale with NO payment (e.g. walk-in changed their mind).
        $this->post('/checkout', [
            'line_groups' => ['retail' => [[
                'inventory_item_id' => $item->id,
                'quantity' => 3,
                'unit_price' => 100,
            ]]],
        ])->assertRedirect();

        $invoice = Invoice::query()->orderByDesc('id')->firstOrFail();
        $this->assertEqualsWithDelta($stockBefore - 3, (float) $item->fresh()->stock_on_hand_cache, 0.001, 'Sale removed stock');

        // Void it → stock returns, invoice becomes terminal.
        $this->post("/invoices/{$invoice->id}/void", ['reason' => 'Customer cancelled'])->assertRedirect();
        $invoice->refresh();
        $this->assertSame('void', $invoice->status->value);
        $this->assertEqualsWithDelta($stockBefore, (float) $item->fresh()->stock_on_hand_cache, 0.001, 'Void restored stock');

        // Voided invoice must NOT appear in the sales report ledger.
        $this->get('/reports/sales?preset=today')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Sales')
                ->where('ledger', fn ($rows) => collect($rows)->every(fn ($r) => $r['invoice_no'] !== $invoice->invoice_no)));
    }

    public function test_a_paid_invoice_cannot_be_voided_until_refunded(): void
    {
        $patient = Patient::query()->firstOrFail();
        $this->post('/checkout', [
            'patient_id' => $patient->id,
            'line_groups' => ['manual' => [['description' => 'Serum', 'quantity' => 1, 'unit_price' => 800]]],
            'payments' => [['method' => 'cash', 'amount' => 800]],
        ])->assertRedirect();

        $invoice = Invoice::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('paid', $invoice->status->value);

        // Voiding a paid invoice is refused.
        $this->post("/invoices/{$invoice->id}/void", ['reason' => 'oops'])
            ->assertSessionHasErrors('status');
        $this->assertSame('paid', $invoice->fresh()->status->value);

        // Refund the full amount → invoice becomes Refunded (terminal).
        $this->post("/invoices/{$invoice->id}/refund", ['amount' => 800, 'method' => 'cash', 'reason' => 'return'])
            ->assertRedirect();
        $invoice->refresh();
        $this->assertSame('refunded', $invoice->status->value);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->amount_paid, 0.001, 'Refund zeroes the balance');
    }

    /** Ground-truth payment aggregates for today, straight from the ledger. */
    private function ledgerToday(): array
    {
        $rows = Payment::query()
            ->whereBetween('paid_at', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->get();
        $gross = round((float) $rows->where('amount', '>', 0)->sum('amount'), 2);
        $refunds = round((float) abs($rows->where('amount', '<', 0)->sum('amount')), 2);

        return [
            'gross' => $gross,
            'refunds' => $refunds,
            'net' => round($gross - $refunds, 2),
            'count' => $rows->where('amount', '>', 0)->count(),
            'cash' => round((float) $rows->where('method', \App\Enums\PaymentMethod::Cash)->sum('amount'), 2),
            'ewallet' => round((float) $rows->where('method', \App\Enums\PaymentMethod::Ewallet)->sum('amount'), 2),
        ];
    }

    /** Assert the report's method breakdown equals the expected per-method sums. */
    private function methodMatches($rows, array $expected): bool
    {
        $actual = collect($rows)->mapWithKeys(fn ($r) => [$r['label'] => round((float) $r['value'], 2)])->all();
        foreach ($expected as $label => $value) {
            if (! isset($actual[$label]) || abs($actual[$label] - $value) > 0.001) {
                return false;
            }
        }

        return true;
    }
}
