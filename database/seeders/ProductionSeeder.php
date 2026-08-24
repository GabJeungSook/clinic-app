<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * First-run baseline for a shipped (offline desktop) install. Idempotent and
 * demo-free: it lays down the branch, roles/permissions, base units, clinic
 * settings, the owner login (admin / password — change on first use), and the
 * real Skinthera service menu. NEVER seed ClinicDemoSeeder/SampleDataSeeder here.
 *
 * Invoked once by NativeAppServiceProvider when the users table is empty.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Branch + roles + units + settings + owner account (all firstOrCreate).
        $this->call(DatabaseSeeder::class);

        // The clinic's actual catalogue (prices/sessions set later in the UI).
        // Runs inside the branch context DatabaseSeeder already pinned.
        $this->call(SkintheraServicesSeeder::class);
    }
}
