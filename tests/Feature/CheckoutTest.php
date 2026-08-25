<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentCourse;
use App\Models\Unit;
use App\Models\User;
use App\Support\Branches\CurrentBranch;
use Database\Seeders\ClinicDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentBranch::flush();
        $this->seed(DatabaseSeeder::class);
        $this->seed(ClinicDemoSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->owner = User::query()->where('username', 'admin')->firstOrFail();
    }

    private function receptionist(): User
    {
        $user = User::factory()->create(['branch_id' => Branch::query()->value('id')]);
        $user->assignRole(RoleEnum::Receptionist->value);

        return $user;
    }

    public function test_checkout_performs_service_with_customised_items(): void
    {
        $service = Service::query()->where('name', 'Signature Facial')->firstOrFail(); // BoM: 3 cotton
        $cotton = InventoryItem::query()->where('name', 'Cotton Balls')->firstOrFail();
        $pc = Unit::query()->where('abbreviation', 'pc')->firstOrFail();
        $patient = Patient::query()->create(['code' => 'P-CO1', 'first_name' => 'Checkout', 'last_name' => 'Tester']);
        $before = $cotton->stockOnHand();

        $this->actingAs($this->owner)->post('/checkout', [
            'patient_id' => $patient->id,
            'notes' => 'Walk-in facial',
            'line_groups' => [
                'services' => [[
                    'service_id' => $service->id,
                    'price' => 1500,
                    'notes' => 'Upper face',
                    // Override: used only 2 cotton instead of the default 3.
                    'consumables' => [['inventory_item_id' => $cotton->id, 'quantity' => 2, 'unit_id' => $pc->id]],
                ]],
            ],
            'payments' => [['method' => 'cash', 'amount' => 1500]],
        ])->assertRedirect();

        $this->assertDatabaseHas('treatment_sessions', ['patient_id' => $patient->id, 'status' => 'completed']);
        $this->assertEqualsWithDelta($before - 2, $cotton->fresh()->stockOnHand(), 0.001);

        $invoice = Invoice::query()->where('patient_id', $patient->id)->latest('created_at')->firstOrFail();
        $this->assertSame('paid', $invoice->status->value);

        $this->actingAs($this->owner)->get("/patients/{$patient->id}")
            ->assertInertia(fn (Assert $page) => $page->has('visits', 1));
    }

    public function test_checkout_service_with_no_items_deducts_nothing(): void
    {
        $service = Service::query()->where('name', 'Signature Facial')->firstOrFail();
        $cotton = InventoryItem::query()->where('name', 'Cotton Balls')->firstOrFail();
        $patient = Patient::query()->firstOrFail();
        $before = $cotton->stockOnHand();

        $this->actingAs($this->owner)->post('/checkout', [
            'patient_id' => $patient->id,
            'line_groups' => ['services' => [[
                'service_id' => $service->id,
                'price' => 1500,
                'consumables' => [],
            ]]],
            'payments' => [['method' => 'cash', 'amount' => 1500]],
        ])->assertRedirect();

        $this->assertEqualsWithDelta($before, $cotton->fresh()->stockOnHand(), 0.001);
    }

    public function test_multi_session_service_creates_a_package(): void
    {
        $service = Service::query()->create([
            'name' => 'Laser Package (Test)',
            'default_session_count' => 6,
            'default_price' => 18000,
        ]);
        $patient = Patient::query()->create(['code' => 'P-PKG', 'first_name' => 'Pkg', 'last_name' => 'Test']);

        // Prepay 6 sessions at ₱3,000 each → a package of 6 (1 performed, 5 left).
        $this->actingAs($this->owner)->post('/checkout', [
            'patient_id' => $patient->id,
            'line_groups' => ['services' => [[
                'service_id' => $service->id,
                'sessions' => 6,
                'price' => 3000,
                'consumables' => [],
            ]]],
            'payments' => [['method' => 'cash', 'amount' => 18000]],
        ])->assertRedirect();

        $course = TreatmentCourse::query()->where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame(6, $course->total_sessions);
        $this->assertSame(1, $course->sessions_completed);
        $this->assertSame(5, $course->sessions_remaining);

        $invoice = Invoice::query()->where('patient_id', $patient->id)->latest('created_at')->firstOrFail();
        $this->assertSame('paid', $invoice->status->value);
        $this->assertEqualsWithDelta(18000.0, (float) $invoice->grand_total, 0.001);
    }

    public function test_prepaying_two_sessions_leaves_one_as_credit(): void
    {
        $service = Service::query()->create([
            'name' => 'Per-session Facial (Test)',
            'default_session_count' => 6,
            'default_price' => 30000,        // ₱5,000 / session
        ]);
        $patient = Patient::query()->create(['code' => 'P-ADV', 'first_name' => 'Adv', 'last_name' => 'Test']);

        // Pay 2 sessions in advance at ₱5,000 each.
        $this->actingAs($this->owner)->post('/checkout', [
            'patient_id' => $patient->id,
            'line_groups' => ['services' => [[
                'service_id' => $service->id,
                'sessions' => 2,
                'price' => 5000,
                'consumables' => [],
            ]]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
        ])->assertRedirect();

        $course = TreatmentCourse::query()->where('patient_id', $patient->id)->firstOrFail();
        $this->assertSame(2, $course->total_sessions);
        $this->assertSame(1, $course->sessions_completed);   // performed today
        $this->assertSame(1, $course->sessions_remaining);   // prepaid credit

        $invoice = Invoice::query()->where('patient_id', $patient->id)->latest('created_at')->firstOrFail();
        $this->assertEqualsWithDelta(10000.0, (float) $invoice->grand_total, 0.001);
    }

    public function test_single_session_checkout_creates_no_package(): void
    {
        $service = Service::query()->create([
            'name' => 'Single Facial (Test)',
            'default_session_count' => 6,
            'default_price' => 30000,
        ]);
        $patient = Patient::query()->create(['code' => 'P-ONE', 'first_name' => 'One', 'last_name' => 'Test']);

        $this->actingAs($this->owner)->post('/checkout', [
            'patient_id' => $patient->id,
            'line_groups' => ['services' => [[
                'service_id' => $service->id,
                'sessions' => 1,
                'price' => 5000,
                'consumables' => [],
            ]]],
            'payments' => [['method' => 'cash', 'amount' => 5000]],
        ])->assertRedirect();

        $this->assertSame(0, TreatmentCourse::query()->where('patient_id', $patient->id)->count());

        $invoice = Invoice::query()->where('patient_id', $patient->id)->latest('created_at')->firstOrFail();
        $this->assertEqualsWithDelta(5000.0, (float) $invoice->grand_total, 0.001);
    }

    public function test_retail_line_deducts_stock_as_sale(): void
    {
        $serum = InventoryItem::query()->where('name', 'Vitamin C Serum')->firstOrFail(); // retail, 40 on hand
        $before = $serum->stockOnHand();

        $this->actingAs($this->owner)->post('/checkout', [
            'line_groups' => ['retail' => [[
                'inventory_item_id' => $serum->id,
                'quantity' => 2,
                'unit_price' => 950,
            ]]],
            'payments' => [['method' => 'cash', 'amount' => 1900]],
        ])->assertRedirect();

        $this->assertEqualsWithDelta($before - 2, $serum->fresh()->stockOnHand(), 0.001);
        $this->assertDatabaseHas('stock_movements', ['inventory_item_id' => $serum->id, 'type' => 'sale_out']);
    }

    public function test_split_payment_leaves_partial_balance(): void
    {
        $this->actingAs($this->owner)->post('/checkout', [
            'line_groups' => ['manual' => [[
                'description' => 'Consultation',
                'quantity' => 1,
                'unit_price' => 1000,
            ]]],
            'payments' => [
                ['method' => 'cash', 'amount' => 500],
                ['method' => 'ewallet', 'amount' => 400],
            ],
        ])->assertRedirect();

        $invoice = Invoice::query()->orderByDesc('id')->firstOrFail(); // newest (ULIDs sort by time)
        $this->assertSame('partially_paid', $invoice->status->value);
        $this->assertEqualsWithDelta(900.0, (float) $invoice->amount_paid, 0.001);
        $this->assertSame(2, $invoice->payments()->count());
    }

    public function test_retail_only_walk_in_needs_no_patient(): void
    {
        $serum = InventoryItem::query()->where('name', 'Vitamin C Serum')->firstOrFail();

        $this->actingAs($this->owner)->post('/checkout', [
            'line_groups' => ['retail' => [[
                'inventory_item_id' => $serum->id,
                'quantity' => 1,
                'unit_price' => 950,
            ]]],
            'payments' => [['method' => 'cash', 'amount' => 950]],
        ])->assertRedirect();

        $invoice = Invoice::query()->orderByDesc('id')->firstOrFail(); // newest (ULIDs sort by time)
        $this->assertNull($invoice->patient_id);
    }

    public function test_out_of_stock_retail_is_rejected(): void
    {
        $pc = Unit::query()->where('abbreviation', 'pc')->firstOrFail();
        $item = InventoryItem::query()->create([
            'name' => 'Empty Serum',
            'type' => 'retail',
            'base_unit_id' => $pc->id,
            'is_batch_tracked' => false,
            'default_sell_price' => 500,
        ]);

        $this->actingAs($this->owner)->post('/checkout', [
            'line_groups' => ['retail' => [[
                'inventory_item_id' => $item->id,
                'quantity' => 1,
                'unit_price' => 500,
            ]]],
        ])->assertSessionHasErrors('line_groups');
    }

    public function test_quick_add_patient_creates_a_record(): void
    {
        $this->actingAs($this->owner)->post('/checkout/patient', [
            'first_name' => 'Quick',
            'last_name' => 'Add',
            'phone' => '0917',
        ])->assertRedirect();

        $this->assertDatabaseHas('patients', ['first_name' => 'Quick', 'last_name' => 'Add']);
    }

    public function test_service_line_requires_a_patient(): void
    {
        $service = Service::query()->where('name', 'Signature Facial')->firstOrFail();

        $this->actingAs($this->owner)->post('/checkout', [
            'line_groups' => ['services' => [[
                'service_id' => $service->id,
                'price' => 1500,
                'consumables' => [],
            ]]],
        ])->assertSessionHasErrors('patient_id');
    }

    public function test_legacy_pos_url_redirects_to_checkout(): void
    {
        $this->actingAs($this->owner)->get('/pos?patient=abc')->assertRedirect('/checkout?patient=abc');
    }

    public function test_receptionist_access_is_limited(): void
    {
        $rec = $this->receptionist();

        $this->actingAs($rec)->get('/dashboard')->assertOk();
        $this->actingAs($rec)->get('/appointments')->assertOk();
        $this->actingAs($rec)->get('/patients')->assertOk();
        $this->actingAs($rec)->get('/checkout')->assertOk();
        $this->actingAs($rec)->get('/reports/revenue')->assertOk();

        $this->actingAs($rec)->get('/inventory')->assertForbidden();
        $this->actingAs($rec)->get('/services')->assertForbidden();
        $this->actingAs($rec)->get('/purchases')->assertForbidden();
        $this->actingAs($rec)->get('/clinic-settings')->assertForbidden();
        $this->actingAs($rec)->get('/users')->assertForbidden();
    }

    public function test_owner_can_create_a_receptionist_account(): void
    {
        $this->actingAs($this->owner)->post('/users', [
            'name' => 'Front Desk',
            'username' => 'frontdesk',
            'role' => RoleEnum::Receptionist->value,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect();

        $user = User::query()->where('username', 'frontdesk')->firstOrFail();
        $this->assertTrue($user->hasRole(RoleEnum::Receptionist->value));
    }
}
