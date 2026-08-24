<?php

namespace Tests\Feature;

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Treatments\CompleteTreatmentSession;
use App\Actions\Treatments\PurchaseTreatmentCourse;
use App\Actions\Treatments\StartTreatmentSession;
use App\Enums\CourseStatus;
use App\Enums\ItemType;
use App\Enums\SessionStatus;
use App\Exceptions\NoSessionsRemainingException;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServiceConsumable;
use App\Models\Unit;
use App\Support\Branches\CurrentBranch;
use Database\Seeders\BranchSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreatmentTest extends TestCase
{
    use RefreshDatabase;

    private Unit $pc;
    private Patient $patient;
    private Service $service;
    private InventoryItem $cotton;
    private InventoryItem $syringe;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentBranch::flush();
        $this->seed(BranchSeeder::class);
        CurrentBranch::set(Branch::query()->value('id'));
        $this->seed(UnitSeeder::class);
        $this->pc = Unit::query()->where('abbreviation', 'pc')->firstOrFail();

        $this->patient = Patient::create([
            'code' => 'P-1', 'first_name' => 'Carla', 'last_name' => 'Reyes',
        ]);
        $this->service = Service::create([
            'name' => 'Facial Package', 'default_session_count' => 3, 'default_price' => 9000,
        ]);

        $this->cotton = $this->makeItem('Cotton');
        $this->syringe = $this->makeItem('Syringe');

        // Bill of materials: each session uses 2 cotton + 1 syringe.
        ServiceConsumable::create([
            'service_id' => $this->service->id, 'inventory_item_id' => $this->cotton->id,
            'quantity' => 2, 'unit_id' => $this->pc->id,
        ]);
        ServiceConsumable::create([
            'service_id' => $this->service->id, 'inventory_item_id' => $this->syringe->id,
            'quantity' => 1, 'unit_id' => $this->pc->id,
        ]);

        // Stock both items generously.
        app(ReceiveStock::class)->handle($this->cotton, 100);
        app(ReceiveStock::class)->handle($this->syringe, 100);
    }

    private function makeItem(string $name): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'type' => ItemType::Consumable,
            'base_unit_id' => $this->pc->id, 'is_batch_tracked' => true, 'track_expiry' => true,
        ]);
    }

    public function test_completing_sessions_decrements_remaining_and_consumes_bom(): void
    {
        $course = app(PurchaseTreatmentCourse::class)->handle($this->patient, $this->service);
        $this->assertSame(3, $course->sessions_remaining);

        $session = app(StartTreatmentSession::class)->handle($this->patient, $this->service, $course);
        $completed = app(CompleteTreatmentSession::class)->handle($session);

        $this->assertSame(SessionStatus::Completed, $completed->status);
        $this->assertSame(1, $completed->session_number);

        $course->refresh();
        $this->assertSame(1, $course->sessions_completed);
        $this->assertSame(2, $course->sessions_remaining);

        // BoM consumed: 2 cotton, 1 syringe.
        $this->assertEqualsWithDelta(98.0, $this->cotton->fresh()->stockOnHand(), 0.001);
        $this->assertEqualsWithDelta(99.0, $this->syringe->fresh()->stockOnHand(), 0.001);
        $this->assertSame(2, $completed->consumptions()->count());
    }

    public function test_course_auto_completes_and_blocks_extra_session(): void
    {
        $course = app(PurchaseTreatmentCourse::class)->handle($this->patient, $this->service);

        for ($i = 0; $i < 3; $i++) {
            $s = app(StartTreatmentSession::class)->handle($this->patient, $this->service, $course);
            app(CompleteTreatmentSession::class)->handle($s);
        }

        $course->refresh();
        $this->assertSame(0, $course->sessions_remaining);
        $this->assertSame(CourseStatus::Completed, $course->status);

        // A 4th session must be refused.
        $extra = app(StartTreatmentSession::class)->handle($this->patient, $this->service, $course);
        $this->expectException(NoSessionsRemainingException::class);
        app(CompleteTreatmentSession::class)->handle($extra);
    }

    public function test_override_allows_completing_beyond_course_total(): void
    {
        $course = app(PurchaseTreatmentCourse::class)->handle($this->patient, $this->service, totalSessions: 1);

        $s1 = app(StartTreatmentSession::class)->handle($this->patient, $this->service, $course);
        app(CompleteTreatmentSession::class)->handle($s1);

        $s2 = app(StartTreatmentSession::class)->handle($this->patient, $this->service, $course);
        $done = app(CompleteTreatmentSession::class)->handle($s2, allowOverride: true);

        $this->assertSame(SessionStatus::Completed, $done->status);
        $this->assertSame(2, $course->fresh()->sessions_completed);
    }

    public function test_bom_shortage_blocks_completion(): void
    {
        // Drain cotton so the BoM can't be fulfilled.
        app(\App\Actions\Inventory\ConsumeStockFefo::class)->handle($this->cotton, 100);
        $this->assertEqualsWithDelta(0.0, $this->cotton->fresh()->stockOnHand(), 0.001);

        $course = app(PurchaseTreatmentCourse::class)->handle($this->patient, $this->service);
        $session = app(StartTreatmentSession::class)->handle($this->patient, $this->service, $course);

        $this->expectException(\App\Exceptions\InsufficientStockException::class);
        app(CompleteTreatmentSession::class)->handle($session);

        // Transaction rolled back: session still not completed, no consumption leaked.
        $this->assertNotSame(SessionStatus::Completed, $session->fresh()->status);
    }
}
