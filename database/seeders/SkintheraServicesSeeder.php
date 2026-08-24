<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Branches\CurrentBranch;
use Illuminate\Database\Seeder;

/**
 * Seeds Skinthera Medical Aesthetic's real service catalogue (from the clinic's
 * menu). Prices and session counts are left at 0 / 1 — the clinic sets those in
 * Services → Edit. Idempotent: safe to re-run.
 */
class SkintheraServicesSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private array $catalog = [
        'Consultation' => [
            'Consultation (Face-to-face)',
            'Consultation (Online)',
        ],
        'Botox' => [
            'Botox – Forehead',
            'Botox – Frown Lines',
            "Botox – Crows' Feet",
            'Jawtox',
            'Alartox',
            'Botox – Bunny Lines',
            'Botox Brow Lift',
            'Botox Neck Lift',
            'Botox Jawline Lift',
            'Sweatox',
            'Traptox',
        ],
        'Steroid Injection' => [
            'Pimple Injection',
            'Keloid Scar Injection',
        ],
        'Acne Scars Treatment' => [
            'Microneedling',
            'Subcision',
            'TCA Cross',
        ],
        'Warts Removal' => [
            'Warts Removal – Face',
            'Warts Removal – Neck',
            'Warts Removal – Body',
        ],
        'Regenerative Skin Boosters' => [
            'Profhilo',
            'Rejuran H',
            'Rejuran S',
            'Rejuran I',
            'Rejuran Hb',
            'Salmon DNA Skin Revive',
        ],
        'Skinthera Mesoheal Series' => [
            'Mesoheal – Korean Glow',
            'Mesoheal – Anti-aging',
            'Mesoheal – Fine Lines & Wrinkles',
        ],
        'Lemon Bottle' => [
            'Lemon Bottle – Double Chin',
            'Lemon Bottle – Upper Arms',
            'Lemon Bottle – Abdomen',
            'Lemon Bottle – Flanks (Love Handle)',
            'Lemon Bottle – Back Fat',
            'Lemon Bottle – Bra Bulge',
            'Lemon Bottle – Inner Thigh',
            'Lemon Bottle – Outer Thigh',
        ],
        'Fillers' => [
            'Lip Filler',
            'Chin Filler',
        ],
        'Drip' => [
            'Youth Recharge Drip (NAD+)',
            'Wellness Drip',
            'White Radiance Drip',
            'Hangover Drip',
            'Shirayuki Drip',
        ],
        'Pico Laser' => [
            'Pico Freckle Refinement',
            'Melasma Precision Therapy',
            'Advance PIH Treatment',
            'Pico Tattoo Removal',
            'Advance Gentle Ink Removal',
            'PicoRefine (Advance Pore Refinement)',
        ],
        'Diode Laser' => [
            'Skinthera LumiGlow (Diode Skin Rejuvenation)',
            'Diode Hair Removal – Legs',
            'Diode Hair Removal – Bikini',
            'Diode Hair Removal – Thigh',
            'Diode Hair Removal – Face',
            'Diode Hair Removal – Chest',
            'Diode Hair Removal – Arms',
            'Diode Hair Removal – Underarms',
            'Diode Hair Removal – Beard',
        ],
        'HIFU' => [
            'HIFU Face Sculpt',
            'HIFU V Lift',
            'HIFU Face and Neck Tightening',
            'HIFU Body Contour',
        ],
        'Others' => [
            'Underarm Whitening',
            'Slimshot',
            'Lip Booster',
            'Varicose Vein & Stretch Marks Treatment',
            'Hair Loss Treatment',
            'Liquid Face Lift',
            'Collagen Stimulating Face Lift (Hiku Thread)',
            'Non-surgical Nose Enhancement (Hiku Nose Thread)',
        ],
    ];

    public function run(): void
    {
        if (Branch::query()->doesntExist()) {
            $this->call(BranchSeeder::class);
        }
        CurrentBranch::flush();
        CurrentBranch::set(Branch::query()->value('id'));

        $order = 1;
        foreach ($this->catalog as $categoryName => $services) {
            $category = ServiceCategory::query()->firstOrCreate(
                ['name' => $categoryName],
                ['sort_order' => $order++],
            );

            foreach ($services as $name) {
                Service::query()->firstOrCreate(
                    ['name' => $name],
                    [
                        'service_category_id' => $category->id,
                        'default_session_count' => 1,
                        'default_price' => 0,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
