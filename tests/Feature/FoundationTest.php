<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Role as RoleEnum;
use App\Enums\SessionStatus;
use App\Models\Branch;
use App\Models\Patient;
use App\Models\Service;
use App\Models\TreatmentCourse;
use App\Models\TreatmentSession;
use App\Models\Unit;
use App\Models\User;
use App\Support\Branches\CurrentBranch;
use App\Support\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentBranch::flush();
        $this->seed(DatabaseSeeder::class);
        CurrentBranch::set(Branch::query()->value('id'));
    }

    public function test_default_branch_and_units_are_seeded(): void
    {
        $this->assertSame(1, Branch::query()->count());
        $this->assertDatabaseHas('branches', ['code' => 'MAIN']);
        $this->assertGreaterThanOrEqual(4, Unit::query()->count());
    }

    public function test_unit_conversion_to_base(): void
    {
        $box = Unit::query()->where('abbreviation', 'box')->first();
        $this->assertNotNull($box);
        $this->assertSame(200.0, $box->toBase(2));
        $this->assertSame('pc', $box->baseUnit->abbreviation);
    }

    public function test_owner_has_role_and_permissions(): void
    {
        $owner = User::query()->where('email', 'owner@clinic.test')->first();
        $this->assertNotNull($owner);
        $this->assertTrue($owner->hasRole(RoleEnum::Owner->value));
        $this->assertTrue($owner->can('billing.manage'));
        $this->assertTrue($owner->can('settings.manage'));
    }

    public function test_settings_defaults_resolve(): void
    {
        $this->assertFalse(Settings::taxEnabled());
        $this->assertSame(12.0, Settings::taxRate());
        $this->assertSame('PHP', Settings::get('billing.currency'));
    }

    public function test_branch_id_is_auto_stamped_on_create(): void
    {
        $patient = Patient::query()->create([
            'code' => 'P-0001',
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
        ]);

        $this->assertSame(CurrentBranch::id(), $patient->branch_id);
    }

    public function test_sessions_remaining_is_derived_from_completed_sessions(): void
    {
        $patient = Patient::query()->create([
            'code' => 'P-0002',
            'first_name' => 'Bea',
            'last_name' => 'Santos',
        ]);
        $service = Service::query()->create([
            'name' => 'Laser Package',
            'default_session_count' => 6,
            'default_price' => 12000,
        ]);
        $course = TreatmentCourse::query()->create([
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'name_snapshot' => $service->name,
            'price_snapshot' => $service->default_price,
            'total_sessions' => 6,
            'status' => CourseStatus::Active,
            'purchased_at' => now(),
        ]);

        $this->assertSame(6, $course->sessions_remaining);

        // Two completed + one cancelled: only completed count.
        foreach ([SessionStatus::Completed, SessionStatus::Completed, SessionStatus::Cancelled] as $i => $status) {
            TreatmentSession::query()->create([
                'treatment_course_id' => $course->id,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'session_number' => $i + 1,
                'status' => $status,
                'performed_at' => now(),
            ]);
        }

        $course->refresh();
        $this->assertSame(2, $course->sessions_completed);
        $this->assertSame(4, $course->sessions_remaining);
    }
}
