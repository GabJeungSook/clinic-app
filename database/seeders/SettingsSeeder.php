<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Branches\CurrentBranch;
use App\Support\Settings\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = CurrentBranch::id();

        foreach (Settings::DEFAULTS as $key => $value) {
            $group = explode('.', $key)[0];

            Setting::query()->firstOrCreate(
                ['branch_id' => $branchId, 'key' => $key],
                ['value' => $value, 'group' => $group],
            );
        }
    }
}
