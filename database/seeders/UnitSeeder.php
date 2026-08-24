<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Support\Branches\CurrentBranch;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = CurrentBranch::id();

        // Base units (factor 1, no parent).
        $bases = [
            ['name' => 'Piece', 'abbreviation' => 'pc'],
            ['name' => 'Milliliter', 'abbreviation' => 'ml'],
            ['name' => 'Gram', 'abbreviation' => 'g'],
            ['name' => 'Vial', 'abbreviation' => 'vial'],
        ];

        $created = [];
        foreach ($bases as $base) {
            $created[$base['abbreviation']] = Unit::query()->firstOrCreate(
                ['branch_id' => $branchId, 'abbreviation' => $base['abbreviation']],
                ['name' => $base['name'], 'factor_to_base' => 1, 'base_unit_id' => null],
            );
        }

        // Derived units expressed against a base.
        $derived = [
            ['name' => 'Box (100 pc)', 'abbreviation' => 'box', 'base' => 'pc', 'factor' => 100],
            ['name' => 'Pack (10 pc)', 'abbreviation' => 'pack', 'base' => 'pc', 'factor' => 10],
            ['name' => 'Liter', 'abbreviation' => 'L', 'base' => 'ml', 'factor' => 1000],
        ];

        foreach ($derived as $d) {
            Unit::query()->firstOrCreate(
                ['branch_id' => $branchId, 'abbreviation' => $d['abbreviation']],
                [
                    'name' => $d['name'],
                    'factor_to_base' => $d['factor'],
                    'base_unit_id' => $created[$d['base']]->id,
                ],
            );
        }
    }
}
