<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Purchase;
use App\Models\Service;
use App\Models\TreatmentCourse;
use App\Models\Unit;
use App\Models\User;
use App\Support\Branches\CurrentBranch;
use Database\Seeders\ClinicDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModulePagesTest extends TestCase
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
    }

    /** @return array<int, array{0:string, 1:string}> */
    public static function pages(): array
    {
        return [
            ['/appointments', 'Appointments/Index'],
            ['/appointments/create', 'Appointments/Create'],
            ['/services', 'Services/Index'],
            ['/services/create', 'Services/Create'],
            ['/inventory', 'Inventory/Index'],
            ['/inventory/create', 'Inventory/Create'],
            ['/inventory/categories', 'Inventory/Categories'],
            ['/inventory/units', 'Inventory/Units'],
            ['/services/categories', 'Services/Categories'],
            ['/suppliers', 'Purchasing/Suppliers'],
            ['/purchases', 'Purchasing/Index'],
            ['/purchases/create', 'Purchasing/Create'],
            ['/treatments', 'Treatments/Index'],
            ['/invoices', 'Billing/Index'],
            ['/checkout', 'Checkout/Create'],
            ['/promotions', 'Billing/Promotions'],
            ['/reports/revenue', 'Reports/Revenue'],
            ['/reports/appointments', 'Reports/Appointments'],
            ['/reports/patients', 'Reports/Patients'],
            ['/reports/inventory', 'Reports/Inventory'],
            ['/reports/purchasing', 'Reports/Purchasing'],
            ['/reports/treatments', 'Reports/Treatments'],
            ['/clinic-settings', 'ClinicSettings'],
        ];
    }

    #[DataProvider('pages')]
    public function test_pages_render(string $url, string $component): void
    {
        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    public function test_treatment_show_renders_session_history(): void
    {
        $course = TreatmentCourse::query()->firstOrFail();

        $this->get("/treatments/{$course->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Treatments/Show')->has('sessions'));
    }

    public function test_purchase_receive_adds_stock(): void
    {
        $item = InventoryItem::query()->where('name', 'Cotton Balls')->firstOrFail();
        $pc = Unit::query()->where('abbreviation', 'pc')->firstOrFail();
        $before = $item->stockOnHand();

        $this->post('/purchases', [
            'lines' => [[
                'inventory_item_id' => $item->id,
                'quantity' => 50,
                'unit_id' => $pc->id,
                'unit_cost' => 2,
            ]],
        ])->assertRedirect();

        $purchase = Purchase::query()->latest('created_at')->firstOrFail();
        $this->post("/purchases/{$purchase->id}/receive")->assertRedirect();

        $this->assertEqualsWithDelta($before + 50, $item->fresh()->stockOnHand(), 0.001);
    }

    public function test_full_billing_flow_checkout_pay_receipt(): void
    {
        $patient = Patient::query()->firstOrFail();
        $service = Service::query()->firstOrFail();

        // Checkout with a partial payment, then settle the balance on the invoice.
        $this->post('/checkout', [
            'patient_id' => $patient->id,
            'line_groups' => ['manual' => [[
                'description' => $service->name,
                'quantity' => 1,
                'unit_price' => 1500,
            ]]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
            'generate_receipt' => true,
        ])->assertRedirect();

        $invoice = \App\Models\Invoice::query()->orderByDesc('id')->firstOrFail(); // newest (ULIDs sort by time)
        $this->assertSame('partially_paid', $invoice->status->value);
        $this->assertDatabaseHas('receipts', ['invoice_id' => $invoice->id]);

        $this->post("/invoices/{$invoice->id}/payments", ['amount' => 500, 'method' => 'ewallet'])
            ->assertRedirect();
        $this->assertSame('paid', $invoice->fresh()->status->value);
    }

    public function test_service_can_be_created_with_bill_of_materials(): void
    {
        $cotton = InventoryItem::query()->where('name', 'Cotton Balls')->firstOrFail();
        $pc = Unit::query()->where('abbreviation', 'pc')->firstOrFail();

        $this->post('/services', [
            'name' => 'New Facial (Test)',
            'default_session_count' => 1,
            'default_price' => 1200,
            'is_active' => true,
            'consumables' => [
                ['inventory_item_id' => $cotton->id, 'quantity' => 3, 'unit_id' => $pc->id],
            ],
        ])->assertRedirect();

        $service = Service::query()->where('name', 'New Facial (Test)')->firstOrFail();
        $this->assertSame(1, $service->consumables()->count());
    }

    public function test_guest_booking_creates_a_patient(): void
    {
        $before = Patient::query()->count();

        $this->post('/appointments', [
            'guest_name' => 'Walkin Caller',
            'guest_phone' => '0917-0000000',
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->assertSame($before + 1, Patient::query()->count());
        $patient = Patient::query()->where('first_name', 'Walkin')->firstOrFail();
        $this->assertDatabaseHas('appointments', ['patient_id' => $patient->id]);
    }

    public function test_appointment_can_be_booked_and_status_changed(): void
    {
        $patient = Patient::query()->firstOrFail();
        $service = Service::query()->firstOrFail();

        $this->post('/appointments', [
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'staff_id' => $this->owner->id,
        ])->assertRedirect();

        $appointment = \App\Models\Appointment::query()->latest('created_at')->firstOrFail();
        $this->assertSame('scheduled', $appointment->status->value);

        $this->post("/appointments/{$appointment->id}/status", ['status' => 'confirmed'])
            ->assertRedirect();
        $this->assertSame('confirmed', $appointment->fresh()->status->value);
    }

    public function test_appointments_calendar_view_renders(): void
    {
        $this->get('/appointments?view=calendar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Appointments/Index')
                ->where('view', 'calendar')
                ->has('calendar.month')
                ->has('calendar.label'));
    }

    public function test_appointment_can_be_booked_from_an_ongoing_package(): void
    {
        $course = TreatmentCourse::query()->where('status', 'active')->firstOrFail();

        $this->post('/appointments', [
            'patient_id' => $course->patient_id,
            'service_id' => $course->service_id,
            'course_id' => $course->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->assertDatabaseHas('appointments', ['course_id' => $course->id, 'patient_id' => $course->patient_id]);
    }

    public function test_booking_requires_patient_or_guest_name(): void
    {
        $this->post('/appointments', [
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('patient_id');
    }

    public function test_inventory_item_can_be_created_and_stock_adjusted(): void
    {
        $unit = Unit::query()->where('abbreviation', 'pc')->firstOrFail();

        $this->post('/inventory', [
            'name' => 'Nitrile Gloves',
            'type' => 'consumable',
            'base_unit_id' => $unit->id,
            'reorder_level' => 10,
            'is_batch_tracked' => false,
            'opening_qty' => 50,
        ])->assertRedirect();

        $item = InventoryItem::query()->where('name', 'Nitrile Gloves')->firstOrFail();
        $this->assertEqualsWithDelta(50.0, $item->stockOnHand(), 0.001);

        $this->get("/inventory/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Inventory/Show'));

        $this->post("/inventory/{$item->id}/adjust", ['quantity' => -5, 'reason' => 'Breakage'])
            ->assertRedirect();
        $this->assertEqualsWithDelta(45.0, $item->fresh()->stockOnHand(), 0.001);
    }

    public function test_inventory_category_and_unit_can_be_managed(): void
    {
        $this->post('/inventory/categories', ['name' => 'Disposables'])->assertRedirect();
        $this->assertDatabaseHas('inventory_categories', ['name' => 'Disposables']);

        $this->post('/inventory/units', ['name' => 'Ampoule', 'abbreviation' => 'amp', 'factor_to_base' => 1])
            ->assertRedirect();
        $this->assertDatabaseHas('units', ['abbreviation' => 'amp']);
    }

    public function test_settings_update_toggles_tax(): void
    {
        $this->put('/clinic-settings', [
            'clinic_name' => 'My Clinic',
            'currency' => 'PHP',
            'currency_symbol' => '₱',
            'tax_enabled' => true,
            'tax_rate' => 12,
            'expiry_threshold_days' => 30,
        ])->assertRedirect();

        $this->assertTrue(\App\Support\Settings\Settings::taxEnabled());
    }
}
