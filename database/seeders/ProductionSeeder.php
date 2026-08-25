<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * First-run baseline for a shipped (offline desktop) install. Idempotent and
 * demo-free (no patients/invoices/appointments): it lays down the branch,
 * roles/permissions, base units, clinic settings, the owner login
 * (admin / password — change on first use), the priced Skinthera service menu,
 * and the inventory catalogue with opening stock + each service's bill of
 * materials. NEVER seed ClinicDemoSeeder/SampleDataSeeder here.
 *
 * If a real go-live should start with ZERO stock, drop ClinicInventorySeeder
 * (or set opening quantities to 0) — the items/BoM would still be seeded.
 *
 * Invoked once by NativeAppServiceProvider when the users table is empty.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Branch + roles + units + settings + owner account (all firstOrCreate).
        $this->call(DatabaseSeeder::class);

        // The clinic's actual catalogue, priced with sessions + durations, plus
        // a stocked inventory and each service's bill of materials.
        $this->call([
            SkintheraServicesSeeder::class,
            ClinicInventorySeeder::class,
        ]);
    }
}
