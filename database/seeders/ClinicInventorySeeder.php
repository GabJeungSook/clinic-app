<?php

namespace Database\Seeders;

use App\Actions\Inventory\ReceiveStock;
use App\Enums\ItemType;
use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Service;
use App\Models\Unit;
use App\Support\Branches\CurrentBranch;
use Illuminate\Database\Seeder;

/**
 * Stocks the clinic with a realistic inventory (consumables, injectables,
 * retail) and wires each service's bill of materials to those items, so a
 * checkout consumes stock and retail sales deduct it. Opening stock is received
 * ~30 days ago via ReceiveStock so batches/costs are real. Idempotent.
 *
 * Depends on services existing — run SkintheraServicesSeeder first (or use
 * ClinicCatalogSeeder, which orders both).
 */
class ClinicInventorySeeder extends Seeder
{
    /** @var array<string, Unit> */
    private array $units = [];
    /** @var array<string, InventoryItem> */
    private array $items = [];

    public function run(): void
    {
        if (Branch::query()->doesntExist()) {
            $this->call(BranchSeeder::class);
        }
        CurrentBranch::flush();
        CurrentBranch::set(Branch::query()->value('id'));

        if (Unit::query()->doesntExist()) {
            $this->call(UnitSeeder::class);
        }
        if (Service::query()->doesntExist()) {
            $this->call(SkintheraServicesSeeder::class);
        }

        foreach (Unit::query()->get() as $u) {
            $this->units[$u->abbreviation] = $u;
        }

        $this->makeInventory();
        $this->attachBillsOfMaterials();
    }

    private function makeInventory(): void
    {
        $cats = [];
        foreach (['Consumables', 'Injectables & Solutions', 'Retail Products'] as $i => $name) {
            $cats[$name] = InventoryCategory::query()->firstOrCreate(['name' => $name], ['sort_order' => $i]);
        }

        // [name, type, unitAbbr, category, reorder, sellPrice, trackExpiry, openingStock, expiryMonths|null, unitCost]
        $defs = [
            // Consumables
            ['Cotton Balls', ItemType::Consumable, 'pc', 'Consumables', 100, 0, false, 1000, null, 1.0],
            ['Gauze Pads', ItemType::Consumable, 'pc', 'Consumables', 80, 0, false, 500, null, 2.0],
            ['Alcohol Solution', ItemType::Consumable, 'ml', 'Consumables', 1000, 0, false, 10000, null, 0.5],
            ['Syringe 1ml', ItemType::Consumable, 'pc', 'Consumables', 100, 0, false, 500, null, 4.0],
            ['Syringe 3ml', ItemType::Consumable, 'pc', 'Consumables', 60, 0, false, 300, null, 5.0],
            ['Needle 30G', ItemType::Consumable, 'pc', 'Consumables', 200, 0, false, 1000, null, 3.0],
            ['Numbing Cream (Lidocaine)', ItemType::Medication, 'g', 'Consumables', 50, 0, true, 300, 18, 20.0],
            ['Micro-needling Cartridge', ItemType::Consumable, 'pc', 'Consumables', 30, 0, false, 120, null, 150.0],
            ['Surgical Gloves', ItemType::Consumable, 'pc', 'Consumables', 200, 0, false, 1000, null, 5.0],
            ['Face Mask', ItemType::Consumable, 'pc', 'Consumables', 200, 0, false, 1000, null, 3.0],
            ['Micropore Tape', ItemType::Consumable, 'box', 'Consumables', 20, 0, false, 50, null, 60.0],
            ['Normal Saline 500ml', ItemType::Consumable, 'ml', 'Consumables', 3000, 0, false, 20000, 24, 0.3],

            // Injectables & Solutions
            ['Botox 100u Vial', ItemType::Medication, 'vial', 'Injectables & Solutions', 3, 0, true, 12, 12, 9000.0],
            ['Dermal Filler 1ml', ItemType::Product, 'ml', 'Injectables & Solutions', 5, 0, true, 20, 18, 3200.0],
            ['Lip Filler HA 1ml', ItemType::Product, 'ml', 'Injectables & Solutions', 5, 0, true, 15, 18, 3500.0],
            ['Rejuran Vial', ItemType::Product, 'vial', 'Injectables & Solutions', 4, 0, true, 12, 10, 6000.0],
            ['Profhilo 2ml', ItemType::Product, 'vial', 'Injectables & Solutions', 3, 0, true, 8, 12, 8000.0],
            ['Lemon Bottle Vial', ItemType::Product, 'vial', 'Injectables & Solutions', 5, 0, true, 24, 12, 1200.0],
            ['Mesotherapy Cocktail', ItemType::Product, 'ml', 'Injectables & Solutions', 200, 0, true, 1000, 10, 15.0],
            ['PDO Threads', ItemType::Product, 'pc', 'Injectables & Solutions', 20, 0, true, 80, 14, 120.0],
            ['Nose Thread (PDO)', ItemType::Product, 'pc', 'Injectables & Solutions', 15, 0, true, 60, 14, 250.0],
            ['NAD+ Vial', ItemType::Product, 'vial', 'Injectables & Solutions', 3, 0, true, 8, 10, 4500.0],
            ['Glutathione Vial', ItemType::Product, 'vial', 'Injectables & Solutions', 5, 0, true, 24, 12, 800.0],
            ['Vitamin C Ampoule', ItemType::Product, 'ml', 'Injectables & Solutions', 50, 0, true, 300, 12, 40.0],

            // Retail Products
            ['Vitamin C Serum', ItemType::Retail, 'pc', 'Retail Products', 10, 950, true, 40, 24, 420.0],
            ['Sunscreen SPF50', ItemType::Retail, 'pc', 'Retail Products', 15, 750, true, 40, 24, 330.0],
            ['Gentle Cleanser', ItemType::Retail, 'pc', 'Retail Products', 10, 650, true, 30, 24, 280.0],
            ['Retinol Cream', ItemType::Retail, 'pc', 'Retail Products', 8, 1200, true, 20, 18, 500.0],
            ['Hydrating Moisturizer', ItemType::Retail, 'pc', 'Retail Products', 10, 800, true, 30, 24, 350.0],
        ];

        $receive = app(ReceiveStock::class);

        foreach ($defs as $d) {
            [$name, $type, $unitAbbr, $catName, $reorder, $sell, $trackExpiry, $stock, $expMonths, $cost] = $d;

            $item = InventoryItem::query()->firstOrCreate(
                ['name' => $name],
                [
                    'inventory_category_id' => $cats[$catName]->id,
                    'sku' => strtoupper(substr(md5($name), 0, 6)),
                    'type' => $type,
                    'base_unit_id' => $this->units[$unitAbbr]->id,
                    'is_batch_tracked' => true,
                    'track_expiry' => $trackExpiry,
                    'reorder_level' => $reorder,
                    'reorder_qty' => $reorder * 2,
                    'default_sell_price' => $sell,
                ],
            );
            $this->items[$name] = $item;

            // Opening batch received ~30 days ago (only if the item has no stock yet).
            if ($item->wasRecentlyCreated) {
                $receive->handle(
                    $item,
                    $stock,
                    expiryDate: $expMonths ? now()->addMonths($expMonths) : null,
                    batchNumber: 'OPEN-' . now()->format('ymd'),
                    unitCost: $cost,
                    occurredAt: now()->subDays(30),
                );
            }
        }
    }

    private function attachBillsOfMaterials(): void
    {
        // service name => [ [itemName, qty, unitAbbr], … ]  (per single session)
        $map = [
            // Botox — a fraction of a 100u vial per area, plus injection basics.
            'Botox – Forehead' => [['Botox 100u Vial', 0.2, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 2, 'pc'], ['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Botox – Frown Lines' => [['Botox 100u Vial', 0.2, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 2, 'pc'], ['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml']],
            "Botox – Crows' Feet" => [['Botox 100u Vial', 0.2, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 2, 'pc'], ['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Jawtox' => [['Botox 100u Vial', 0.5, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 2, 'pc'], ['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Alartox' => [['Botox 100u Vial', 0.1, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Sweatox' => [['Botox 100u Vial', 1, 'vial'], ['Syringe 1ml', 2, 'pc'], ['Needle 30G', 4, 'pc'], ['Cotton Balls', 3, 'pc'], ['Alcohol Solution', 8, 'ml']],
            'Traptox' => [['Botox 100u Vial', 1, 'vial'], ['Syringe 1ml', 2, 'pc'], ['Needle 30G', 4, 'pc'], ['Cotton Balls', 3, 'pc'], ['Alcohol Solution', 8, 'ml']],

            // Steroid injections
            'Pimple Injection' => [['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 1, 'pc'], ['Alcohol Solution', 3, 'ml']],
            'Keloid Scar Injection' => [['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 3, 'ml']],

            // Acne scars
            'Microneedling' => [['Micro-needling Cartridge', 1, 'pc'], ['Cotton Balls', 3, 'pc'], ['Gauze Pads', 2, 'pc'], ['Numbing Cream (Lidocaine)', 2, 'g'], ['Surgical Gloves', 2, 'pc']],
            'Subcision' => [['Needle 30G', 2, 'pc'], ['Syringe 3ml', 1, 'pc'], ['Cotton Balls', 3, 'pc'], ['Numbing Cream (Lidocaine)', 2, 'g'], ['Surgical Gloves', 2, 'pc']],
            'TCA Cross' => [['Cotton Balls', 2, 'pc'], ['Gauze Pads', 1, 'pc'], ['Surgical Gloves', 2, 'pc']],

            // Warts
            'Warts Removal – Face' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml'], ['Gauze Pads', 1, 'pc'], ['Surgical Gloves', 2, 'pc']],
            'Warts Removal – Neck' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml'], ['Surgical Gloves', 2, 'pc']],
            'Warts Removal – Body' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml'], ['Surgical Gloves', 2, 'pc']],

            // Skin boosters
            'Profhilo' => [['Profhilo 2ml', 1, 'vial'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Rejuran H' => [['Rejuran Vial', 1, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Rejuran S' => [['Rejuran Vial', 1, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Rejuran I' => [['Rejuran Vial', 1, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Rejuran Hb' => [['Rejuran Vial', 1, 'vial'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Salmon DNA Skin Revive' => [['Mesotherapy Cocktail', 2, 'ml'], ['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc']],

            // Mesoheal (mesotherapy)
            'Mesoheal – Korean Glow' => [['Mesotherapy Cocktail', 3, 'ml'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Mesoheal – Anti-aging' => [['Mesotherapy Cocktail', 3, 'ml'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Mesoheal – Fine Lines & Wrinkles' => [['Mesotherapy Cocktail', 3, 'ml'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],

            // Lemon Bottle (fat dissolving)
            'Lemon Bottle – Double Chin' => [['Lemon Bottle Vial', 1, 'vial'], ['Syringe 3ml', 2, 'pc'], ['Needle 30G', 4, 'pc'], ['Cotton Balls', 3, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Lemon Bottle – Upper Arms' => [['Lemon Bottle Vial', 1, 'vial'], ['Syringe 3ml', 2, 'pc'], ['Needle 30G', 4, 'pc'], ['Cotton Balls', 3, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Lemon Bottle – Abdomen' => [['Lemon Bottle Vial', 2, 'vial'], ['Syringe 3ml', 3, 'pc'], ['Needle 30G', 6, 'pc'], ['Cotton Balls', 4, 'pc'], ['Alcohol Solution', 8, 'ml']],
            'Lemon Bottle – Flanks (Love Handle)' => [['Lemon Bottle Vial', 2, 'vial'], ['Syringe 3ml', 3, 'pc'], ['Needle 30G', 6, 'pc'], ['Cotton Balls', 4, 'pc'], ['Alcohol Solution', 8, 'ml']],
            'Lemon Bottle – Back Fat' => [['Lemon Bottle Vial', 2, 'vial'], ['Syringe 3ml', 3, 'pc'], ['Needle 30G', 6, 'pc'], ['Cotton Balls', 4, 'pc'], ['Alcohol Solution', 8, 'ml']],
            'Lemon Bottle – Bra Bulge' => [['Lemon Bottle Vial', 1, 'vial'], ['Syringe 3ml', 2, 'pc'], ['Needle 30G', 4, 'pc'], ['Cotton Balls', 3, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Lemon Bottle – Inner Thigh' => [['Lemon Bottle Vial', 2, 'vial'], ['Syringe 3ml', 3, 'pc'], ['Needle 30G', 6, 'pc'], ['Cotton Balls', 4, 'pc'], ['Alcohol Solution', 8, 'ml']],
            'Lemon Bottle – Outer Thigh' => [['Lemon Bottle Vial', 2, 'vial'], ['Syringe 3ml', 3, 'pc'], ['Needle 30G', 6, 'pc'], ['Cotton Balls', 4, 'pc'], ['Alcohol Solution', 8, 'ml']],

            // Fillers
            'Lip Filler' => [['Lip Filler HA 1ml', 1, 'ml'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],
            'Chin Filler' => [['Dermal Filler 1ml', 1, 'ml'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g']],

            // Drips (IV)
            'Youth Recharge Drip (NAD+)' => [['NAD+ Vial', 1, 'vial'], ['Normal Saline 500ml', 250, 'ml'], ['Syringe 3ml', 1, 'pc'], ['Cotton Balls', 2, 'pc']],
            'Wellness Drip' => [['Normal Saline 500ml', 250, 'ml'], ['Vitamin C Ampoule', 5, 'ml'], ['Cotton Balls', 2, 'pc']],
            'White Radiance Drip' => [['Glutathione Vial', 1, 'vial'], ['Vitamin C Ampoule', 5, 'ml'], ['Normal Saline 500ml', 250, 'ml'], ['Cotton Balls', 2, 'pc']],
            'Hangover Drip' => [['Normal Saline 500ml', 500, 'ml'], ['Cotton Balls', 2, 'pc']],
            'Shirayuki Drip' => [['Glutathione Vial', 1, 'vial'], ['Normal Saline 500ml', 250, 'ml'], ['Cotton Balls', 2, 'pc']],

            // Pico / Diode laser (minimal consumables)
            'Pico Freckle Refinement' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml'], ['Gauze Pads', 1, 'pc']],
            'Melasma Precision Therapy' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Advance PIH Treatment' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Pico Tattoo Removal' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml'], ['Gauze Pads', 1, 'pc']],
            'PicoRefine (Advance Pore Refinement)' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Skinthera LumiGlow (Diode Skin Rejuvenation)' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 5, 'ml']],
            'Diode Hair Removal – Legs' => [['Cotton Balls', 2, 'pc'], ['Alcohol Solution', 3, 'ml']],
            'Diode Hair Removal – Underarms' => [['Cotton Balls', 1, 'pc'], ['Alcohol Solution', 3, 'ml']],
            'Diode Hair Removal – Face' => [['Cotton Balls', 1, 'pc'], ['Alcohol Solution', 3, 'ml']],

            // HIFU
            'HIFU Face Sculpt' => [['Cotton Balls', 2, 'pc'], ['Gauze Pads', 1, 'pc']],
            'HIFU V Lift' => [['Cotton Balls', 2, 'pc'], ['Gauze Pads', 1, 'pc']],
            'HIFU Face and Neck Tightening' => [['Cotton Balls', 3, 'pc'], ['Gauze Pads', 1, 'pc']],
            'HIFU Body Contour' => [['Cotton Balls', 2, 'pc'], ['Gauze Pads', 1, 'pc']],

            // Others
            'Underarm Whitening' => [['Mesotherapy Cocktail', 2, 'ml'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc']],
            'Hair Loss Treatment' => [['Mesotherapy Cocktail', 3, 'ml'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc']],
            'Lip Booster' => [['Syringe 1ml', 1, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc']],
            'Liquid Face Lift' => [['Dermal Filler 1ml', 2, 'ml'], ['Needle 30G', 2, 'pc'], ['Cotton Balls', 3, 'pc'], ['Numbing Cream (Lidocaine)', 2, 'g']],
            'Collagen Stimulating Face Lift (Hiku Thread)' => [['PDO Threads', 10, 'pc'], ['Needle 30G', 2, 'pc'], ['Cotton Balls', 3, 'pc'], ['Numbing Cream (Lidocaine)', 2, 'g'], ['Surgical Gloves', 2, 'pc']],
            'Non-surgical Nose Enhancement (Hiku Nose Thread)' => [['Nose Thread (PDO)', 4, 'pc'], ['Needle 30G', 1, 'pc'], ['Cotton Balls', 2, 'pc'], ['Numbing Cream (Lidocaine)', 1, 'g'], ['Surgical Gloves', 2, 'pc']],
        ];

        foreach ($map as $serviceName => $lines) {
            $service = Service::query()->where('name', $serviceName)->first();
            if (! $service) {
                continue;
            }
            foreach ($lines as [$itemName, $qty, $unitAbbr]) {
                if (! isset($this->items[$itemName]) || ! isset($this->units[$unitAbbr])) {
                    continue;
                }
                $service->consumables()->updateOrCreate(
                    ['inventory_item_id' => $this->items[$itemName]->id],
                    ['quantity' => $qty, 'unit_id' => $this->units[$unitAbbr]->id],
                );
            }
        }
    }
}
