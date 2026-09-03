<?php

namespace Database\Seeders;

use App\Actions\Billing\CreateInvoice;
use App\Actions\Billing\GenerateReceipt;
use App\Actions\Billing\RecordPayment;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Purchasing\ReceivePurchase;
use App\Actions\Treatments\CompleteTreatmentSession;
use App\Actions\Treatments\PurchaseTreatmentCourse;
use App\Actions\Treatments\StartTreatmentSession;
use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Enums\MedicalHistoryType;
use App\Enums\FaceShape;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\Role as RoleEnum;
use App\Enums\SkinType;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Purchase;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Support\Branches\CurrentBranch;
use App\Support\Chart\ChartOptions;
use App\Support\DocumentNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Generates a rich, backdated dataset (patients, inventory, purchases,
 * treatment courses with performed sessions, invoices/payments and
 * appointments) so every report and dashboard chart has meaningful data.
 *
 *   php artisan db:seed --class=SampleDataSeeder
 */
class SampleDataSeeder extends Seeder
{
    /** @var array<string, Unit> */
    private array $units = [];
    /** @var array<string, InventoryItem> */
    private array $items = [];
    /** @var array<int, User> */
    private array $staff = [];

    public function run(): void
    {
        $this->ensureFoundation();

        $this->staff = $this->makeStaff();
        $this->makeInventory();
        $this->makePurchases();
        $this->makeSupplierCoverage();
        $this->attachBillsOfMaterials();
        $this->setServiceCosts();
        $patients = $this->makePatients();
        $this->makeTreatmentsAndBilling($patients);
        $this->makeExtraCourses($patients);
        $this->makeRetailSales($patients);
        $this->makeAppointments($patients);
        $this->makeExpenses();
    }

    private function ensureFoundation(): void
    {
        if (Branch::query()->doesntExist()) {
            $this->call(BranchSeeder::class);
        }
        CurrentBranch::flush();
        CurrentBranch::set(Branch::query()->value('id'));

        if (Unit::query()->doesntExist()) {
            $this->call([UnitSeeder::class, RoleSeeder::class, SettingsSeeder::class]);
        }
        if (Service::query()->doesntExist()) {
            $this->call(SkintheraServicesSeeder::class);
        }

        foreach (Unit::query()->get() as $u) {
            $this->units[$u->abbreviation] = $u;
        }
    }

    /** @return array<int, User> */
    private function makeStaff(): array
    {
        $branchId = CurrentBranch::id();
        $defs = [
            ['name' => 'Dr. Maria Santos', 'username' => 'dr.santos', 'role' => RoleEnum::Doctor, 'job' => 'Dermatologist'],
            ['name' => 'Nurse Joy Tan', 'username' => 'nurse.joy', 'role' => RoleEnum::Doctor, 'job' => 'Aesthetic Nurse'],
            ['name' => 'Ella Reyes', 'username' => 'reception', 'role' => RoleEnum::Receptionist, 'job' => 'Receptionist'],
        ];

        $staff = [];
        foreach ($defs as $d) {
            $u = User::query()->firstOrCreate(
                ['username' => $d['username']],
                [
                    'branch_id' => $branchId,
                    'name' => $d['name'],
                    'email' => $d['username'] . '@skinthera.test',
                    'job_title' => $d['job'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
            $u->syncRoles([$d['role']->value]);
            $staff[] = $u;
        }

        return $staff;
    }

    private function makeInventory(): void
    {
        $cats = [];
        foreach (['Consumables', 'Injectables', 'Retail Products'] as $i => $name) {
            $cats[$name] = InventoryCategory::query()->firstOrCreate(['name' => $name], ['sort_order' => $i]);
        }

        // [name, type, unit, category, reorder, sell, trackExpiry, initialStock, expiryMonths|null, extraExpiredQty]
        $defs = [
            ['Cotton Balls', ItemType::Consumable, 'pc', 'Consumables', 100, 0, false, 500, null, 0],
            ['Gauze Pads', ItemType::Consumable, 'pc', 'Consumables', 80, 0, false, 30, null, 0],       // low
            ['Syringe 1ml', ItemType::Consumable, 'pc', 'Consumables', 60, 0, false, 320, null, 0],
            ['Syringe 3ml', ItemType::Consumable, 'pc', 'Consumables', 40, 0, false, 150, null, 0],
            ['Alcohol Solution', ItemType::Consumable, 'ml', 'Consumables', 1000, 0, false, 5000, null, 0],
            ['Botox 100u Vial', ItemType::Medication, 'vial', 'Injectables', 3, 0, true, 8, 12, 0],
            ['Dermal Filler 1ml', ItemType::Product, 'ml', 'Injectables', 5, 0, true, 18, 1, 3],          // near expiry + expired
            ['Rejuran Vial', ItemType::Product, 'vial', 'Injectables', 4, 0, true, 2, 10, 0],             // low
            ['PDO Threads', ItemType::Product, 'pc', 'Injectables', 20, 0, true, 40, 14, 0],
            ['Vitamin C Serum', ItemType::Retail, 'pc', 'Retail Products', 10, 950, true, 25, 18, 0],
            ['Sunscreen SPF50', ItemType::Retail, 'pc', 'Retail Products', 15, 750, true, 22, 24, 0],
        ];

        $receive = app(ReceiveStock::class);

        foreach ($defs as $d) {
            [$name, $type, $unitAbbr, $catName, $reorder, $sell, $trackExpiry, $stock, $expMonths, $expiredQty] = $d;

            $item = InventoryItem::query()->firstOrCreate(
                ['name' => $name],
                [
                    'inventory_category_id' => $cats[$catName]->id,
                    'sku' => strtoupper(substr(md5($name), 0, 6)),
                    'type' => $type,
                    'base_unit_id' => $this->units[$unitAbbr]->id,
                    'is_batch_tracked' => true,
                    'track_expiry' => $trackExpiry,
                    'reorder_level' => $reorder,
                    'reorder_qty' => $reorder * 2,
                    'default_sell_price' => $sell,
                ],
            );
            $this->items[$name] = $item;

            // Main opening batch ~30 days ago.
            $receive->handle(
                $item,
                $stock,
                expiryDate: $expMonths ? now()->addMonths($expMonths) : null,
                batchNumber: 'OPEN-' . now()->format('ymd'),
                unitCost: $this->costFor($name),
                occurredAt: now()->subDays(30),
            );

            // A near-expiry batch for a couple of injectables (drives the alert).
            if ($name === 'Dermal Filler 1ml') {
                $receive->handle($item, 4, expiryDate: now()->addDays(15), batchNumber: 'FL-SOON', unitCost: 3200, occurredAt: now()->subDays(20));
            }
            if ($name === 'Botox 100u Vial') {
                $receive->handle($item, 2, expiryDate: now()->addDays(25), batchNumber: 'BX-SOON', unitCost: 9000, occurredAt: now()->subDays(18));
            }

            // An already-expired batch (shows in expiry report).
            if ($expiredQty > 0) {
                $receive->handle($item, $expiredQty, expiryDate: now()->subDays(8), batchNumber: 'OLD-EXP', unitCost: 3000, occurredAt: now()->subDays(60));
            }
        }
    }

    private function costFor(string $name): float
    {
        return match ($name) {
            'Botox 100u Vial' => 9000,
            'Dermal Filler 1ml' => 3200,
            'Rejuran Vial' => 6000,
            'PDO Threads' => 120,
            'Vitamin C Serum' => 420,
            'Sunscreen SPF50' => 330,
            'Syringe 1ml', 'Syringe 3ml' => 4,
            default => 1.5,
        };
    }

    private function makePurchases(): void
    {
        $suppliers = [];
        foreach ([
            ['name' => 'MedSupply PH', 'contact_name' => 'Grace Lim', 'phone' => '0917 100 2000'],
            ['name' => 'Aesthetic Distributors Inc.', 'contact_name' => 'Paolo Cruz', 'phone' => '0918 200 3000'],
            ['name' => 'DermaSource Trading', 'contact_name' => 'Nina Yu', 'phone' => '0919 300 4000'],
        ] as $s) {
            $suppliers[] = Supplier::query()->firstOrCreate(['name' => $s['name']], $s);
        }

        // Two received purchases (add stock, recent) and one still on order.
        $this->makePurchase($suppliers[0], now()->subDays(10), PurchaseStatus::Received, [
            ['Cotton Balls', 500, 'pc', 1.5],
            ['Syringe 1ml', 200, 'pc', 4],
        ]);
        $this->makePurchase($suppliers[1], now()->subDays(5), PurchaseStatus::Received, [
            ['Dermal Filler 1ml', 6, 'ml', 3200],
            ['Botox 100u Vial', 4, 'vial', 9000],
        ]);
        $this->makePurchase($suppliers[2], now()->subDays(1), PurchaseStatus::Ordered, [
            ['PDO Threads', 50, 'pc', 120],
            ['Rejuran Vial', 6, 'vial', 6000],
        ]);
    }

    /** @param array<int, array{0:string,1:float,2:string,3:float}> $lines */
    private function makePurchase(Supplier $supplier, CarbonInterface $date, PurchaseStatus $status, array $lines): void
    {
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'reference_no' => DocumentNumber::next(Purchase::query(), 'PO', 'reference_no'),
            'status' => PurchaseStatus::Draft,
            'ordered_at' => $date,
            'created_by' => $this->staff[2]->id ?? null,
        ]);

        foreach ($lines as [$itemName, $qty, $unitAbbr, $cost]) {
            $purchase->items()->create([
                'inventory_item_id' => $this->items[$itemName]->id,
                'quantity' => $qty,
                'unit_id' => $this->units[$unitAbbr]->id,
                'unit_cost' => $cost,
            ]);
        }

        if ($status === PurchaseStatus::Received) {
            app(ReceivePurchase::class)->handle($purchase->fresh('items'), $this->staff[2]->id ?? null);
        } else {
            $purchase->update(['status' => $status]);
        }
    }

    private function attachBillsOfMaterials(): void
    {
        $pc = $this->units['pc']->id;
        $ml = $this->units['ml']->id;
        $vial = $this->units['vial']->id;

        $map = [
            'Botox – Forehead' => [['Botox 100u Vial', 0.25, $vial], ['Syringe 1ml', 1, $pc], ['Cotton Balls', 2, $pc], ['Alcohol Solution', 5, $ml]],
            'Lip Filler' => [['Dermal Filler 1ml', 1, $ml], ['Syringe 1ml', 1, $pc], ['Cotton Balls', 2, $pc]],
            'Chin Filler' => [['Dermal Filler 1ml', 1, $ml], ['Syringe 1ml', 1, $pc], ['Cotton Balls', 2, $pc]],
            'Rejuran H' => [['Rejuran Vial', 1, $vial], ['Syringe 1ml', 1, $pc], ['Cotton Balls', 2, $pc]],
            'Microneedling' => [['Cotton Balls', 3, $pc], ['Gauze Pads', 2, $pc]],
            'HIFU Face Sculpt' => [['Cotton Balls', 2, $pc], ['Gauze Pads', 1, $pc]],
            'Diode Hair Removal – Legs' => [['Cotton Balls', 2, $pc]],
            'Pico Freckle Refinement' => [['Cotton Balls', 2, $pc], ['Alcohol Solution', 5, $ml]],
        ];

        foreach ($map as $serviceName => $lines) {
            $service = Service::query()->where('name', $serviceName)->first();
            if (! $service) {
                continue;
            }
            foreach ($lines as [$itemName, $qty, $unitId]) {
                if (! isset($this->items[$itemName])) {
                    continue;
                }
                $service->consumables()->updateOrCreate(
                    ['inventory_item_id' => $this->items[$itemName]->id],
                    ['quantity' => $qty, 'unit_id' => $unitId],
                );
            }
        }
    }

    /** @return array<int, Patient> */
    private function makePatients(): array
    {
        $patients = [];
        for ($i = 1; $i <= 24; $i++) {
            $p = Patient::create([
                'code' => 'P-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'date_of_birth' => fake()->dateTimeBetween('-55 years', '-19 years')->format('Y-m-d'),
                'sex' => fake()->randomElement(['male', 'female', 'female', 'female']),
                'occupation' => fake()->jobTitle(),
                'civil_status' => fake()->randomElement(['Single', 'Married', 'Widowed', 'Separated']),
                'phone' => '09' . fake()->numerify('## ### ####'),
                'email' => fake()->optional(0.5)->safeEmail(),
                'created_at' => now()->subDays(rand(1, 60)),
            ]);

            // A few patients get an allergy flag.
            if ($i % 5 === 0) {
                $p->medicalHistories()->create([
                    'type' => MedicalHistoryType::Allergy,
                    'title' => fake()->randomElement(['Allergic to lidocaine', 'Allergic to penicillin', 'Latex sensitivity']),
                    'recorded_at' => now()->subDays(rand(1, 40)),
                ]);
            }

            // Most patients get a populated clinical chart so demos aren't empty.
            if ($i % 6 !== 0) {
                $this->makeChart($p);
            }

            $patients[] = $p;
        }

        return $patients;
    }

    /** Populate a realistic clinical chart for the given patient. */
    private function makeChart(Patient $patient): void
    {
        $pickKeys = fn (array $map, int $max) => (array) fake()->randomElements(
            array_keys($map),
            rand(0, min($max, count($map))),
        );

        $procedures = [];
        foreach (array_keys(ChartOptions::PROCEDURES) as $key) {
            $done = fake()->boolean(30);
            $procedures[$key] = [
                'done' => $done,
                'when' => $done ? fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d') : null,
            ];
        }

        $patient->chart()->create([
            'history_flags' => [
                'have' => $pickKeys(ChartOptions::HAVE, 2),
                'have_others' => null,
                'taking' => $pickKeys(ChartOptions::TAKING, 1),
                'taking_others' => null,
                'condition' => $pickKeys(ChartOptions::CONDITION, 1),
                'condition_others' => null,
            ],
            'procedures_done' => $procedures,
            'lifestyle' => [
                'avg_sleep' => rand(5, 8) . ' hours',
                'eating_habits' => fake()->randomElement(['Balanced diet', 'High sugar intake', 'Vegetarian', 'Irregular meals']),
                'exercise' => fake()->boolean(),
                'past_medical_history' => fake()->optional(0.4)->sentence(),
                'previous_surgery' => fake()->optional(0.2)->sentence(),
            ],
            'derma_history' => [
                'had_consult' => $consult = fake()->boolean(40),
                'reason' => $consult ? fake()->randomElement(['Acne management', 'Anti-aging', 'Pigmentation concerns']) : null,
                'when' => $consult ? fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d') : null,
            ],
            'initial_plan' => [
                'items' => $pickKeys(ChartOptions::INITIAL_PLAN, 3),
                'items_others' => null,
            ],
            'physician_notes' => [[
                'observations' => fake()->sentence(),
                'test_ordered' => fake()->optional(0.3)->randomElement(['Skin analysis', 'Patch test']),
                'results' => fake()->optional(0.3)->randomElement(['Normal', 'Mild sensitivity']),
                'additional_notes' => fake()->optional(0.5)->sentence(),
            ]],
            'assessment_conditions' => [
                'conditions' => $pickKeys(ChartOptions::ASSESSMENT, 4),
                'conditions_others' => null,
            ],
            'beauty_plan' => [
                ['procedure' => 'Facial Peel', 'price' => 3500, 'timeline' => 'Monthly'],
                ['procedure' => fake()->randomElement(['Microneedling', 'HIFU Face Sculpt', 'Rejuran H']), 'price' => rand(8, 25) * 1000, 'timeline' => fake()->randomElement(['3 sessions', '6 months', 'Quarterly'])],
            ],
            'skin_type' => fake()->randomElement(SkinType::cases())->value,
            'face_shape' => fake()->randomElement(FaceShape::cases())->value,
            'findings' => fake()->optional(0.7)->paragraph(),
            'medical_record' => fake()->optional(0.8)->paragraphs(2, true),
            'procedures_notes' => fake()->optional(0.5)->sentence(),
            'lifestyle_notes' => fake()->optional(0.5)->sentence(),
            'initial_plan_notes' => fake()->optional(0.5)->sentence(),
            'assessment_notes' => fake()->optional(0.5)->sentence(),
            'beauty_plan_notes' => fake()->optional(0.5)->sentence(),
        ]);
    }

    /** Give every service a per-session cost (~30–45% of its price) for gross/net reporting. */
    private function setServiceCosts(): void
    {
        foreach (Service::query()->get() as $service) {
            if ((float) $service->cost > 0) {
                continue;
            }
            $perSession = (float) $service->default_price / max(1, (int) $service->default_session_count);
            $service->cost = round($perSession * fake()->randomFloat(2, 0.30, 0.45), 2);
            $service->save();
        }
    }

    /** Attach a supplier to every item that has no purchase history yet (via an ordered PO — no stock change). */
    private function makeSupplierCoverage(): void
    {
        $suppliers = Supplier::query()->orderBy('name')->get();
        if ($suppliers->isEmpty()) {
            return;
        }

        $items = InventoryItem::query()->whereDoesntHave('purchaseItems')->get()->values();
        $bySupplier = [];
        foreach ($items as $idx => $item) {
            $bySupplier[$suppliers[$idx % $suppliers->count()]->id][] = $item;
        }

        foreach ($bySupplier as $supplierId => $group) {
            $purchase = Purchase::create([
                'supplier_id' => $supplierId,
                'reference_no' => DocumentNumber::next(Purchase::query(), 'PO', 'reference_no'),
                'status' => PurchaseStatus::Ordered,
                'ordered_at' => now()->subDays(rand(2, 20)),
                'created_by' => $this->staff[2]->id ?? null,
            ]);
            foreach ($group as $item) {
                $purchase->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity' => (float) $item->reorder_qty > 0 ? (float) $item->reorder_qty : 10,
                    'unit_id' => $item->base_unit_id,
                    'unit_cost' => 100,
                ]);
            }
        }
    }

    /** Give the first few patients extra treatment courses so some have multiple services. */
    private function makeExtraCourses(array $patients): void
    {
        $names = [
            'Diode Hair Removal – Legs', 'HIFU Face Sculpt', 'Microneedling', 'Rejuran H',
            'Botox – Forehead', 'Lip Filler', 'Pico Freckle Refinement', 'Mesoheal – Korean Glow',
        ];
        $services = Service::query()->whereIn('name', $names)->get()->keyBy('name');
        if ($services->isEmpty()) {
            return;
        }

        foreach (array_slice($patients, 0, 6) as $patient) {
            foreach (collect($names)->shuffle()->take(rand(1, 2)) as $name) {
                $service = $services[$name] ?? null;
                if (! $service) {
                    continue;
                }

                // Use the service's natural package size + price (realistic totals).
                $sessions = max(1, (int) $service->default_session_count);
                // Extra courses don't consume stock (keeps demo inventory healthy).
                $this->seedCourse($patient, $service, $sessions, (float) $service->default_price, now()->subDays(rand(3, 40)), consumeStock: false);
            }
        }
    }

    /** Notes reused across seeded sessions. */
    private const SESSION_NOTES = [
        'Patient tolerated the procedure well. No adverse reactions noted.',
        'Mild erythema post-treatment; advised cold compress and sun protection.',
        'Visible improvement since last session. Continuing the planned schedule.',
        'Good response. Recommended hydrating serum and daily SPF for aftercare.',
        'Reduced redness compared to the previous visit. Patient happy with progress.',
    ];

    /**
     * Seed one treatment package end-to-end and *consistently*, so the data can
     * be traced through the system:
     *   - a course of $sessions at a clean per-session price;
     *   - a realistic number of performed sessions on past dates;
     *   - an invoice billed PER SESSION (qty = sessions, not one lump line); and
     *   - a payment that matches the scenario, so money paid always reconciles
     *     with the sessions performed:
     *       • paid        — prepaid in full (may be ongoing or completed)
     *       • installment — pay-as-you-go: paid = sessions performed so far
     *       • deposit     — invoiced but unpaid, no sessions yet (outstanding)
     */
    private function seedCourse(Patient $patient, Service $service, int $sessions, float $price, CarbonInterface $purchasedAt, bool $consumeStock): void
    {
        $perSession = round($price / max(1, $sessions), 2);

        // Pick a scenario and how many sessions to actually perform.
        if ($sessions === 1) {
            [$scenario, $intended] = rand(1, 10) <= 8 ? ['paid', 1] : ['deposit', 0];
        } else {
            $roll = rand(1, 10);
            [$scenario, $intended] = match (true) {
                $roll <= 6 => ['paid', rand(1, $sessions)],
                $roll <= 9 => ['installment', rand(1, $sessions - 1)],
                default => ['deposit', 0],
            };
        }

        $course = app(PurchaseTreatmentCourse::class)->handle(
            $patient,
            $service,
            totalSessions: $sessions,
            price: round($perSession * $sessions, 2),
            purchasedAt: $purchasedAt,
        );

        $performer = $this->staff[array_rand($this->staff)]->id;
        $start = app(StartTreatmentSession::class);
        $complete = app(CompleteTreatmentSession::class);

        $performed = 0;
        for ($n = 0; $n < $intended; $n++) {
            $performedAt = (clone $purchasedAt)->addDays($n * rand(7, 14))->setTime(rand(9, 16), rand(0, 3) * 15);
            if ($performedAt->isFuture()) {
                break;
            }
            $session = $start->handle($patient, $service, $course, performedBy: $performer);
            if ($consumeStock) {
                $complete->handle($session, performedBy: $performer, allowOverride: true, performedAt: $performedAt);
            } else {
                $complete->handle($session, performedBy: $performer, consumptionOverrides: [], allowOverride: true, performedAt: $performedAt);
            }
            $session->update(['clinical_notes' => self::SESSION_NOTES[array_rand(self::SESSION_NOTES)]]);
            $performed++;
        }

        // Bill the package PER SESSION so the invoice reconciles with the sessions.
        $desc = $sessions > 1 ? "{$course->name_snapshot} (package of {$sessions})" : $course->name_snapshot;
        $invoice = app(CreateInvoice::class)->handle(
            $patient,
            [['description' => $desc, 'quantity' => $sessions, 'unit_price' => $perSession, 'itemable' => $course]],
            status: InvoiceStatus::Unpaid,
            createdBy: $performer,
            issuedAt: $purchasedAt,
        );

        // Link the course back to its invoice (the real checkout does this too),
        // so the package's paid/unpaid status can be traced from the course.
        $course->forceFill(['invoice_id' => $invoice->id])->save();

        $grand = (float) $invoice->grand_total;
        $pay = match ($scenario) {
            'paid' => $grand,
            'installment' => $performed > 0 ? round($grand * $performed / $sessions, 2) : 0.0,
            default => 0.0,
        };

        if ($pay > 0) {
            app(RecordPayment::class)->handle($invoice, $pay, PaymentMethod::Cash, receivedBy: $performer, paidAt: $purchasedAt);
            if ($pay >= $grand) {
                app(GenerateReceipt::class)->handle($invoice->fresh());
            }
        }
    }

    /** Some register cash-outs so the Expenses page and profitability have data. */
    private function makeExpenses(): void
    {
        if (Expense::query()->exists()) {
            return;
        }

        $recordedBy = $this->staff[2]->id ?? ($this->staff[0]->id ?? null);
        $defs = [
            ['Bought alcohol & cotton', 'Supplies', 850],
            ['Lunch for staff', 'Food/Meals', 620],
            ['Grab delivery of supplies', 'Transport', 240],
            ['Aircon cleaning', 'Maintenance', 1500],
            ['Water refill', 'Utilities', 90],
            ['Printer ink', 'Supplies', 780],
            ['Load for clinic phone', 'Utilities', 300],
            ['Cleaning supplies', 'Supplies', 450],
            ['Staff merienda', 'Food/Meals', 380],
        ];
        foreach ($defs as $i => [$description, $category, $amount]) {
            Expense::create([
                'description' => $description,
                'category' => $category,
                'amount' => $amount,
                'spent_at' => now()->subDays($i * 3),
                'recorded_by' => $recordedBy,
            ]);
        }
    }

    /** @param array<int, Patient> $patients */
    private function makeTreatmentsAndBilling(array $patients): void
    {
        // [service name, total sessions, course price] — prices divide evenly per session.
        $templates = [
            ['Diode Hair Removal – Legs', 6, 18000],       // 3,000 / session
            ['Diode Hair Removal – Underarms', 6, 12000],  // 2,000 / session
            ['Pico Freckle Refinement', 4, 20000],         // 5,000 / session
            ['HIFU Face Sculpt', 3, 24000],                // 8,000 / session
            ['Microneedling', 4, 16000],                   // 4,000 / session
            ['Rejuran H', 3, 24000],                       // 8,000 / session
            ['Botox – Forehead', 1, 8000],
            ['Lip Filler', 1, 15000],
            ['Mesoheal – Korean Glow', 4, 14000],          // 3,500 / session
        ];

        foreach (array_slice($patients, 0, 18) as $idx => $patient) {
            [$serviceName, $sessions, $price] = $templates[$idx % count($templates)];
            $service = Service::query()->where('name', $serviceName)->first();
            if (! $service) {
                continue;
            }

            $this->seedCourse($patient, $service, $sessions, (float) $price, now()->subDays(rand(3, 40)), consumeStock: true);
        }
    }

    /** @param array<int, Patient> $patients Some over-the-counter retail sales for revenue variety. */
    private function makeRetailSales(array $patients): void
    {
        $createInvoice = app(CreateInvoice::class);
        $recordPayment = app(RecordPayment::class);
        $serum = $this->items['Vitamin C Serum'] ?? null;
        $sunscreen = $this->items['Sunscreen SPF50'] ?? null;
        if (! $serum || ! $sunscreen) {
            return;
        }

        for ($i = 0; $i < 12; $i++) {
            $patient = $patients[array_rand($patients)];
            $soldAt = now()->subDays(rand(0, 13))->setTime(rand(10, 17), 0);
            $product = fake()->randomElement([$serum, $sunscreen]);
            $qty = rand(1, 2);
            $invoice = $createInvoice->handle(
                $patient,
                [['description' => $product->name, 'quantity' => $qty, 'unit_price' => (float) $product->default_sell_price, 'itemable' => $product]],
                status: InvoiceStatus::Unpaid,
                issuedAt: $soldAt,
            );
            $recordPayment->handle($invoice, (float) $invoice->grand_total, PaymentMethod::Ewallet, paidAt: $soldAt);
        }
    }

    /** @param array<int, Patient> $patients */
    private function makeAppointments(array $patients): void
    {
        $services = Service::query()->where('is_active', true)->inRandomOrder()->limit(20)->get();

        // Past appointments (completed / no-show / cancelled).
        for ($i = 0; $i < 12; $i++) {
            $this->makeAppointment(
                $patients[array_rand($patients)],
                $services->random(),
                now()->subDays(rand(1, 13))->setTime(rand(9, 17), rand(0, 3) * 15),
                fake()->randomElement([AppointmentStatus::Completed, AppointmentStatus::Completed, AppointmentStatus::NoShow, AppointmentStatus::Cancelled]),
            );
        }

        // Upcoming appointments (scheduled / confirmed), including a few today.
        for ($i = 0; $i < 14; $i++) {
            $when = $i < 4
                ? now()->setTime(rand(9, 17), rand(0, 3) * 15)          // today
                : now()->addDays(rand(1, 13))->setTime(rand(9, 17), rand(0, 3) * 15);

            // Occasionally a not-yet-registered caller.
            $patient = $i % 6 === 0 ? null : $patients[array_rand($patients)];
            $this->makeAppointment(
                $patient,
                $services->random(),
                $when,
                fake()->randomElement([AppointmentStatus::Scheduled, AppointmentStatus::Confirmed]),
            );
        }
    }

    private function makeAppointment(?Patient $patient, Service $service, CarbonInterface $when, AppointmentStatus $status): void
    {
        Appointment::create([
            'patient_id' => $patient?->id,
            'guest_name' => $patient ? null : fake()->name(),
            'guest_phone' => $patient ? null : '09' . fake()->numerify('## ### ####'),
            'service_id' => $service->id,
            'staff_id' => $this->staff[array_rand($this->staff)]->id,
            'scheduled_at' => $when,
            'duration_minutes' => $service->duration_minutes ?? 30,
            'status' => $status,
            'created_by' => $this->staff[2]->id ?? null,
        ]);
    }
}
