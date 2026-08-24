<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Models\Branch;
use App\Models\User;
use App\Support\Branches\CurrentBranch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Foundation must run first: branch, then everything branch-scoped.
        $this->call(BranchSeeder::class);

        // Pin the current branch for the rest of the seed run (console has no auth).
        CurrentBranch::flush();
        CurrentBranch::set(Branch::query()->value('id'));

        $this->call([
            RoleSeeder::class,
            UnitSeeder::class,
            SettingsSeeder::class,
        ]);

        $branchId = CurrentBranch::id();

        $owner = User::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'branch_id' => $branchId,
                'name' => 'Clinic Owner',
                'email' => 'owner@clinic.test',
                'job_title' => 'Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
        $owner->assignRole(RoleEnum::Owner->value);
    }
}
