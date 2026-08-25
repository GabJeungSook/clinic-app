<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Branches\CurrentBranch;
use Illuminate\Database\Seeder;

/**
 * Seeds Skinthera Medical Aesthetic's real service catalogue, priced and ready
 * to sell: each entry carries a package price, session count, and appointment
 * duration. `default_price` is the WHOLE package total (per-session × sessions);
 * the checkout / course purchase charges that amount. Idempotent (updateOrCreate).
 */
class SkintheraServicesSeeder extends Seeder
{
    /**
     * category => [ [name, packagePrice, sessions, durationMinutes], … ]
     *
     * @var array<string, array<int, array{0:string,1:int,2:int,3:int}>>
     */
    private array $catalog = [
        'Consultation' => [
            ['Consultation (Face-to-face)', 500, 1, 30],
            ['Consultation (Online)', 300, 1, 20],
        ],
        'Botox' => [
            ['Botox – Forehead', 12000, 1, 30],
            ['Botox – Frown Lines', 10000, 1, 30],
            ["Botox – Crows' Feet", 10000, 1, 30],
            ['Jawtox', 18000, 1, 30],
            ['Alartox', 8000, 1, 30],
            ['Botox – Bunny Lines', 7000, 1, 30],
            ['Botox Brow Lift', 9000, 1, 30],
            ['Botox Neck Lift', 25000, 1, 45],
            ['Botox Jawline Lift', 20000, 1, 45],
            ['Sweatox', 25000, 1, 45],
            ['Traptox', 28000, 1, 45],
        ],
        'Steroid Injection' => [
            ['Pimple Injection', 800, 1, 15],
            ['Keloid Scar Injection', 2500, 1, 20],
        ],
        'Acne Scars Treatment' => [
            ['Microneedling', 16000, 4, 45],
            ['Subcision', 6000, 1, 45],
            ['TCA Cross', 10500, 3, 30],
        ],
        'Warts Removal' => [
            ['Warts Removal – Face', 3000, 1, 30],
            ['Warts Removal – Neck', 3500, 1, 30],
            ['Warts Removal – Body', 4000, 1, 30],
        ],
        'Regenerative Skin Boosters' => [
            ['Profhilo', 36000, 2, 45],
            ['Rejuran H', 36000, 3, 45],
            ['Rejuran S', 39000, 3, 45],
            ['Rejuran I', 42000, 3, 45],
            ['Rejuran Hb', 45000, 3, 45],
            ['Salmon DNA Skin Revive', 30000, 3, 45],
        ],
        'Skinthera Mesoheal Series' => [
            ['Mesoheal – Korean Glow', 14000, 4, 45],
            ['Mesoheal – Anti-aging', 16000, 4, 45],
            ['Mesoheal – Fine Lines & Wrinkles', 16000, 4, 45],
        ],
        'Lemon Bottle' => [
            ['Lemon Bottle – Double Chin', 10500, 3, 30],
            ['Lemon Bottle – Upper Arms', 12000, 3, 30],
            ['Lemon Bottle – Abdomen', 15000, 3, 45],
            ['Lemon Bottle – Flanks (Love Handle)', 13500, 3, 45],
            ['Lemon Bottle – Back Fat', 13500, 3, 45],
            ['Lemon Bottle – Bra Bulge', 12000, 3, 30],
            ['Lemon Bottle – Inner Thigh', 13500, 3, 45],
            ['Lemon Bottle – Outer Thigh', 13500, 3, 45],
        ],
        'Fillers' => [
            ['Lip Filler', 15000, 1, 45],
            ['Chin Filler', 16000, 1, 45],
        ],
        'Drip' => [
            ['Youth Recharge Drip (NAD+)', 8000, 1, 60],
            ['Wellness Drip', 3500, 1, 45],
            ['White Radiance Drip', 4500, 1, 45],
            ['Hangover Drip', 3000, 1, 45],
            ['Shirayuki Drip', 5000, 1, 45],
        ],
        'Pico Laser' => [
            ['Pico Freckle Refinement', 20000, 4, 30],
            ['Melasma Precision Therapy', 33000, 6, 30],
            ['Advance PIH Treatment', 30000, 6, 30],
            ['Pico Tattoo Removal', 24000, 6, 45],
            ['Advance Gentle Ink Removal', 27000, 6, 45],
            ['PicoRefine (Advance Pore Refinement)', 20000, 4, 30],
        ],
        'Diode Laser' => [
            ['Skinthera LumiGlow (Diode Skin Rejuvenation)', 16000, 4, 30],
            ['Diode Hair Removal – Legs', 18000, 6, 45],
            ['Diode Hair Removal – Bikini', 12000, 6, 30],
            ['Diode Hair Removal – Thigh', 15000, 6, 45],
            ['Diode Hair Removal – Face', 10800, 6, 30],
            ['Diode Hair Removal – Chest', 15000, 6, 30],
            ['Diode Hair Removal – Arms', 13200, 6, 30],
            ['Diode Hair Removal – Underarms', 9000, 6, 20],
            ['Diode Hair Removal – Beard', 12000, 6, 20],
        ],
        'HIFU' => [
            ['HIFU Face Sculpt', 25000, 1, 60],
            ['HIFU V Lift', 30000, 1, 60],
            ['HIFU Face and Neck Tightening', 35000, 1, 75],
            ['HIFU Body Contour', 20000, 1, 60],
        ],
        'Others' => [
            ['Underarm Whitening', 10000, 4, 30],
            ['Slimshot', 12000, 6, 20],
            ['Lip Booster', 8000, 1, 30],
            ['Varicose Vein & Stretch Marks Treatment', 18000, 3, 45],
            ['Hair Loss Treatment', 30000, 6, 45],
            ['Liquid Face Lift', 35000, 1, 60],
            ['Collagen Stimulating Face Lift (Hiku Thread)', 40000, 1, 90],
            ['Non-surgical Nose Enhancement (Hiku Nose Thread)', 25000, 1, 60],
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

            foreach ($services as [$name, $price, $sessions, $duration]) {
                Service::query()->updateOrCreate(
                    ['name' => $name],
                    [
                        'service_category_id' => $category->id,
                        'default_session_count' => $sessions,
                        'default_price' => $price,
                        'duration_minutes' => $duration,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
