<?php

namespace Tests\Feature;

use App\Actions\Billing\CreateInvoice;
use App\Actions\Billing\GenerateReceipt;
use App\Actions\Billing\RecordPayment;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PromotionScope;
use App\Enums\PromotionType;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Promotion;
use App\Support\Branches\CurrentBranch;
use App\Support\Settings\Settings;
use Database\Seeders\BranchSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentBranch::flush();
        $this->seed(BranchSeeder::class);
        CurrentBranch::set(Branch::query()->value('id'));
        $this->patient = Patient::create(['code' => 'P-1', 'first_name' => 'Dina', 'last_name' => 'Lim']);
    }

    private function lines(): array
    {
        return [
            ['description' => 'Service A', 'quantity' => 1, 'unit_price' => 1000],
            ['description' => 'Product B', 'quantity' => 2, 'unit_price' => 500],
        ];
    }

    public function test_invoice_without_tax(): void
    {
        $invoice = app(CreateInvoice::class)->handle($this->patient, $this->lines());

        $this->assertEqualsWithDelta(2000.0, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->tax_total, 0.001);
        $this->assertEqualsWithDelta(2000.0, (float) $invoice->grand_total, 0.001);
        $this->assertCount(2, $invoice->items);
    }

    public function test_invoice_with_exclusive_tax(): void
    {
        Settings::set('billing.tax_enabled', true, 'billing');
        Settings::set('billing.tax_rate', 12, 'billing');

        $invoice = app(CreateInvoice::class)->handle($this->patient, $this->lines());

        $this->assertEqualsWithDelta(2000.0, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(240.0, (float) $invoice->tax_total, 0.001);
        $this->assertEqualsWithDelta(2240.0, (float) $invoice->grand_total, 0.001);
        // Per-line tax allocation sums to the total.
        $this->assertEqualsWithDelta(240.0, (float) $invoice->items->sum('tax'), 0.001);
    }

    public function test_invoice_with_inclusive_tax_extracts_portion(): void
    {
        Settings::set('billing.tax_enabled', true, 'billing');
        Settings::set('billing.tax_rate', 12, 'billing');
        Settings::set('billing.tax_inclusive', true, 'billing');

        $invoice = app(CreateInvoice::class)->handle($this->patient, [
            ['description' => 'All-in', 'quantity' => 1, 'unit_price' => 1120],
        ]);

        // 1120 inclusive of 12% => tax portion 120, grand stays 1120.
        $this->assertEqualsWithDelta(120.0, (float) $invoice->tax_total, 0.01);
        $this->assertEqualsWithDelta(1120.0, (float) $invoice->grand_total, 0.001);
    }

    public function test_line_discount_and_invoice_promotion(): void
    {
        $promo = Promotion::create([
            'name' => '10% off', 'type' => PromotionType::Percent, 'value' => 10,
            'applies_to' => PromotionScope::Invoice, 'is_active' => true,
        ]);

        $lines = $this->lines();
        $lines[0]['discount'] = 100; // manual line discount

        $invoice = app(CreateInvoice::class)->handle(
            $this->patient, $lines, invoicePromotionId: $promo->id
        );

        // Line nets: 900 + 1000 = 1900; invoice promo 10% => 190 discount => 1710.
        $this->assertEqualsWithDelta(1900.0, (float) $invoice->subtotal, 0.001);
        $this->assertEqualsWithDelta(190.0, (float) $invoice->discount_total, 0.001);
        $this->assertEqualsWithDelta(1710.0, (float) $invoice->grand_total, 0.001);
        $this->assertSame(1, $promo->fresh()->used_count);
    }

    public function test_promotion_only_applies_within_its_date_window(): void
    {
        $base = ['type' => PromotionType::Percent, 'value' => 10, 'applies_to' => PromotionScope::Invoice, 'is_active' => true];

        $scheduled = Promotion::create([...$base, 'name' => 'Xmas (future)', 'valid_from' => now()->addDays(5)]);
        $expired = Promotion::create([...$base, 'name' => 'Last week', 'valid_to' => now()->subDay()]);
        $active = Promotion::create([...$base, 'name' => 'Long weekend', 'valid_from' => now()->subDay(), 'valid_to' => now()->addDay()]);

        // Scheduled → no discount.
        $inv = app(CreateInvoice::class)->handle($this->patient, $this->lines(), invoicePromotionId: $scheduled->id);
        $this->assertEqualsWithDelta(2000.0, (float) $inv->grand_total, 0.001);

        // Expired → no discount.
        $inv = app(CreateInvoice::class)->handle($this->patient, $this->lines(), invoicePromotionId: $expired->id);
        $this->assertEqualsWithDelta(2000.0, (float) $inv->grand_total, 0.001);

        // Active window → 10% off.
        $inv = app(CreateInvoice::class)->handle($this->patient, $this->lines(), invoicePromotionId: $active->id);
        $this->assertEqualsWithDelta(1800.0, (float) $inv->grand_total, 0.001);
    }

    public function test_promotion_respects_minimum_spend(): void
    {
        $promo = Promotion::create([
            'name' => 'Spend 5k get 10%', 'type' => PromotionType::Percent, 'value' => 10,
            'applies_to' => PromotionScope::Invoice, 'is_active' => true, 'min_spend' => 5000,
        ]);

        // Subtotal 2000 < 5000 → not applied.
        $inv = app(CreateInvoice::class)->handle($this->patient, $this->lines(), invoicePromotionId: $promo->id);
        $this->assertEqualsWithDelta(2000.0, (float) $inv->grand_total, 0.001);
    }

    public function test_payments_drive_status(): void
    {
        $invoice = app(CreateInvoice::class)->handle($this->patient, $this->lines());
        $record = app(RecordPayment::class);

        $record->handle($invoice, 500, PaymentMethod::Cash);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertEqualsWithDelta(500.0, (float) $invoice->fresh()->amount_paid, 0.001);

        $record->handle($invoice, 1500, PaymentMethod::Card);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertEqualsWithDelta(0.0, $invoice->fresh()->amountDue(), 0.001);
    }

    public function test_tax_snapshot_is_immutable_when_setting_toggled_later(): void
    {
        // Issued with tax OFF.
        $invoice = app(CreateInvoice::class)->handle($this->patient, $this->lines());
        $this->assertFalse((bool) $invoice->tax_enabled);
        $originalGrand = (float) $invoice->grand_total;

        // Later, tax is switched ON globally.
        Settings::set('billing.tax_enabled', true, 'billing');

        $invoice->refresh();
        $this->assertFalse((bool) $invoice->tax_enabled, 'historical invoice keeps its snapshot');
        $this->assertEqualsWithDelta($originalGrand, (float) $invoice->grand_total, 0.001);
    }

    public function test_receipt_snapshot_is_generated(): void
    {
        $invoice = app(CreateInvoice::class)->handle($this->patient, $this->lines());
        app(RecordPayment::class)->handle($invoice, 2000);

        $receipt = app(GenerateReceipt::class)->handle($invoice->fresh());

        $this->assertNotNull($receipt->receipt_no);
        $this->assertEqualsWithDelta(2000.0, (float) $receipt->snapshot['totals']['grand_total'], 0.001);
        $this->assertCount(2, $receipt->snapshot['items']);
    }
}
