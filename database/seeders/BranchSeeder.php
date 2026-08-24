<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Branch',
                'timezone' => 'Asia/Manila',
                'is_active' => true,
            ]
        );
    }
}
