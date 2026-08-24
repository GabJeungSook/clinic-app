<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use App\Support\Branches\CurrentBranch;
use Database\Seeders\ClinicDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SmokeTest extends TestCase
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
    }

    public function test_dashboard_renders_with_stats(): void
    {
        $this->actingAs($this->owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats')
                ->has('lowStock')
                ->has('expiringSoon'));
    }

    public function test_patients_index_lists_seeded_patients(): void
    {
        $this->actingAs($this->owner)
            ->get('/patients')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Patients/Index')
                ->has('patients.data', 3));
    }

    public function test_patient_can_be_created_and_shown(): void
    {
        $this->actingAs($this->owner)
            ->post('/patients', ['first_name' => 'New', 'last_name' => 'Person'])
            ->assertRedirect();

        $patient = Patient::query()->where('last_name', 'Person')->firstOrFail();
        $this->assertNotEmpty($patient->code);

        $this->actingAs($this->owner)
            ->get("/patients/{$patient->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Patients/Show'));
    }

    public function test_inventory_index_renders(): void
    {
        $this->actingAs($this->owner)
            ->get('/inventory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventory/Index')
                ->has('items.data'));
    }

    public function test_dashboard_shows_expiring_filler_batch(): void
    {
        $response = $this->actingAs($this->owner)->get('/dashboard');
        $response->assertInertia(fn (Assert $page) => $page
            ->where('stats.expiring_soon_count', fn ($n) => $n >= 1));
    }
}
