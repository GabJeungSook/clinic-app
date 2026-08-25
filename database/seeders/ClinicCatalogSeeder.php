<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * A clean, ready-to-test baseline: foundation (branch, roles, units, settings,
 * owner login admin/password), the priced Skinthera service menu, and a stocked
 * inventory with each service's bill of materials — but NO demo patients,
 * invoices or appointments. Use this to test the system end to end:
 *
 *   php artisan migrate:fresh
 *   php artisan db:seed --class=ClinicCatalogSeeder
 */
class ClinicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DatabaseSeeder::class,          // branch, roles, units, settings, owner
            SkintheraServicesSeeder::class, // priced services (price + sessions + duration)
            ClinicInventorySeeder::class,   // inventory items + opening stock + service BoM
        ]);
    }
}
