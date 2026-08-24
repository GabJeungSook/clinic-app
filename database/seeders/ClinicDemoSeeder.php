<?php

namespace Database\Seeders;

use App\Actions\Billing\CreateInvoice;
use App\Actions\Billing\GenerateReceipt;
use App\Actions\Billing\RecordPayment;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Treatments\CompleteTreatmentSession;
use App\Actions\Treatments\PurchaseTreatmentCourse;
use App\Actions\Treatments\StartTreatmentSession;
use App\Enums\ItemType;
use App\Enums\PaymentMethod;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\Service;
use App\Models\ServiceConsumable;
use App\Models\Unit;
use App\Support\Branches\CurrentBranch;
use Illuminate\Database\Seeder;

/**
 * Realistic demo data built by driving the real Action classes, so the seeded
 * ledger, sessions and invoices are internally consistent. Run after the
 * foundation seeders: php artisan db:seed --class=ClinicDemoSeeder
 */
class ClinicDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Branch::query()->doesntExist()) {
            $this->call(BranchSeeder::class);
        }
        CurrentBranch::flush();
        CurrentBranch::set(Branch::query()->value('id'));

        if (Unit::query()->doesntExist()) {
            $this->call(UnitSeeder::class);
            $this->call(SettingsSeeder::class);
        }

        $pc = Unit::query()->where('abbreviation', 'pc')->firstOrFail();
        $ml = Unit::query()->where('abbreviation', 'ml')->firstOrFail();

        // --- Inventory items ---------------------------------------------------
        $cotton = $this->item('Cotton Balls', ItemType::Consumable, $pc, reorder: 50);
        $syringe = $this->item('Syringe 1ml', ItemType::Consumable, $pc, reorder: 30);
        $filler = $this->item('Dermal Filler', ItemType::Product, $ml, reorder: 5);
        $serum = $this->item('Vitamin C Serum', ItemType::Retail, $pc, reorder: 10, sell: 950);

        $receive = app(ReceiveStock::class);
        $receive->handle($cotton, 200, batchNumber: 'CT-A', unitCost: 1.5);
        $receive->handle($syringe, 150, batchNumber: 'SY-A', unitCost: 4);
        // Two filler batches: one expiring soon (drives the dashboard alert).
        $receive->handle($filler, 10, expiryDate: now()->addDays(20), batchNumber: 'FL-EXP', unitCost: 3000);
        $receive->handle($filler, 20, expiryDate: now()->addMonths(10), batchNumber: 'FL-NEW', unitCost: 3000);
        $receive->handle($serum, 40, batchNumber: 'VC-A', unitCost: 400);

        // --- Services + bill of materials -------------------------------------
        $facial = $this->service('Signature Facial', 1, 1500);
        $laser = $this->service('Laser Hair Removal', 6, 18000);
        $fillerSvc = $this->service('Dermal Filler Treatment', 1, 15000);

        $this->bom($facial, $cotton, 3, $pc);
        $this->bom($laser, $cotton, 2, $pc);
        $this->bom($laser, $syringe, 1, $pc);
        $this->bom($fillerSvc, $filler, 1, $ml);
        $this->bom($fillerSvc, $syringe, 1, $pc);
        $this->bom($fillerSvc, $cotton, 2, $pc);

        // --- Patients ----------------------------------------------------------
        $ana = $this->patient('P-0001', 'Ana', 'Cruz', '0917 111 2222');
        $bea = $this->patient('P-0002', 'Bea', 'Santos', '0918 333 4444');
        $this->patient('P-0003', 'Carlo', 'Reyes', '0919 555 6666');

        // --- A laser course with 2 of 6 sessions completed --------------------
        $course = app(PurchaseTreatmentCourse::class)->handle($ana, $laser);
        for ($i = 0; $i < 2; $i++) {
            $session = app(StartTreatmentSession::class)->handle($ana, $laser, $course);
            app(CompleteTreatmentSession::class)->handle($session);
        }

        // Invoice for the laser course + a retail serum, fully paid + receipt.
        $invoice = app(CreateInvoice::class)->handle($ana, [
            ['description' => $course->name_snapshot, 'quantity' => 1, 'unit_price' => (float) $course->price_snapshot, 'itemable' => $course],
            ['description' => $serum->name, 'quantity' => 1, 'unit_price' => (float) $serum->default_sell_price, 'itemable' => $serum],
        ]);
        app(RecordPayment::class)->handle($invoice, (float) $invoice->grand_total, PaymentMethod::Cash);
        app(GenerateReceipt::class)->handle($invoice->fresh());

        // An unpaid facial invoice for Bea.
        app(CreateInvoice::class)->handle($bea, [
            ['description' => $facial->name, 'quantity' => 1, 'unit_price' => (float) $facial->default_price, 'itemable' => $facial],
        ]);
    }

    private function item(string $name, ItemType $type, Unit $unit, float $reorder = 0, float $sell = 0): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'sku' => strtoupper(substr(md5($name), 0, 6)),
            'type' => $type,
            'base_unit_id' => $unit->id,
            'is_batch_tracked' => true,
            'track_expiry' => in_array($type, [ItemType::Product, ItemType::Medication], true),
            'reorder_level' => $reorder,
            'reorder_qty' => $reorder * 2,
            'default_sell_price' => $sell,
        ]);
    }

    private function service(string $name, int $sessions, float $price): Service
    {
        return Service::create([
            'name' => $name,
            'default_session_count' => $sessions,
            'default_price' => $price,
            'is_active' => true,
        ]);
    }

    private function bom(Service $service, InventoryItem $item, float $qty, Unit $unit): void
    {
        ServiceConsumable::create([
            'service_id' => $service->id,
            'inventory_item_id' => $item->id,
            'quantity' => $qty,
            'unit_id' => $unit->id,
        ]);
    }

    private function patient(string $code, string $first, string $last, string $phone): Patient
    {
        return Patient::create([
            'code' => $code,
            'first_name' => $first,
            'last_name' => $last,
            'phone' => $phone,
        ]);
    }
}
