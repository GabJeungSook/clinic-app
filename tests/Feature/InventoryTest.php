<?php

namespace Tests\Feature;

use App\Actions\Inventory\ConsumeStockFefo;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Inventory\WriteOffExpiredStock;
use App\Actions\Purchasing\ReceivePurchase;
use App\Enums\ItemType;
use App\Enums\PurchaseStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Unit;
use App\Support\Branches\CurrentBranch;
use Database\Seeders\BranchSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Unit $pc;

    protected function setUp(): void
    {
        parent::setUp();
        CurrentBranch::flush();
        $this->seed(BranchSeeder::class);
        CurrentBranch::set(Branch::query()->value('id'));
        $this->seed(UnitSeeder::class);
        $this->pc = Unit::query()->where('abbreviation', 'pc')->firstOrFail();
    }

    private function item(bool $batchTracked = true): InventoryItem
    {
        return InventoryItem::query()->create([
            'name' => 'Syringe 1ml',
            'type' => ItemType::Consumable,
            'base_unit_id' => $this->pc->id,
            'is_batch_tracked' => $batchTracked,
            'track_expiry' => $batchTracked,
            'reorder_level' => 20,
        ]);
    }

    public function test_receiving_stock_increases_on_hand_and_records_movement(): void
    {
        $item = $this->item();
        app(ReceiveStock::class)->handle($item, 50, unitCost: 3.0);

        $item->refresh();
        $this->assertEqualsWithDelta(50.0, $item->stockOnHand(), 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $item->stock_on_hand_cache, 0.001);
        $this->assertDatabaseCount('batches', 1);
        $this->assertDatabaseHas('stock_movements', ['inventory_item_id' => $item->id, 'type' => 'purchase_in']);
    }

    public function test_fefo_consumes_nearest_expiry_first_across_batches(): void
    {
        $item = $this->item();
        $receive = app(ReceiveStock::class);

        // Batch A expires later; Batch B expires sooner.
        $batchA = $receive->handle($item, 10, expiryDate: now()->addMonths(12), batchNumber: 'A');
        $batchB = $receive->handle($item, 10, expiryDate: now()->addMonths(2), batchNumber: 'B');

        $movements = app(ConsumeStockFefo::class)->handle($item, 12);

        $this->assertCount(2, $movements); // spans both batches
        $this->assertEqualsWithDelta(0.0, $batchB->fresh()->qtyRemaining(), 0.001, 'sooner-expiry batch drained first');
        $this->assertEqualsWithDelta(8.0, $batchA->fresh()->qtyRemaining(), 0.001);
        $this->assertEqualsWithDelta(8.0, $item->fresh()->stockOnHand(), 0.001);
    }

    public function test_consume_refuses_when_insufficient(): void
    {
        $item = $this->item();
        app(ReceiveStock::class)->handle($item, 5);

        $this->expectException(InsufficientStockException::class);
        app(ConsumeStockFefo::class)->handle($item, 10);
    }

    public function test_consume_override_allows_going_short(): void
    {
        $item = $this->item();
        app(ReceiveStock::class)->handle($item, 5);

        $movements = app(ConsumeStockFefo::class)->handle($item, 8, allowOverride: true);

        $this->assertNotEmpty($movements);
        $this->assertEqualsWithDelta(-3.0, $item->fresh()->stockOnHand(), 0.001);
    }

    public function test_write_off_expired_zeroes_the_batch(): void
    {
        $item = $this->item();
        $batch = app(ReceiveStock::class)->handle($item, 10, expiryDate: now()->subDay(), batchNumber: 'OLD');

        app(WriteOffExpiredStock::class)->handle($batch->fresh());

        $this->assertEqualsWithDelta(0.0, $batch->fresh()->qtyRemaining(), 0.001);
        $this->assertEqualsWithDelta(0.0, $item->fresh()->stockOnHand(), 0.001);
        $this->assertDatabaseHas('stock_movements', ['batch_id' => $batch->id, 'type' => 'expiry_writeoff']);
    }

    public function test_non_batch_tracked_item_consumes_without_batch(): void
    {
        $item = $this->item(batchTracked: false);
        // Seed on-hand via an adjustment-style receive (no batch semantics needed).
        app(ReceiveStock::class)->handle($item, 100);

        $movements = app(ConsumeStockFefo::class)->handle($item, 30);

        $this->assertCount(1, $movements);
        $this->assertNull($movements[0]->batch_id);
        $this->assertEqualsWithDelta(70.0, $item->fresh()->stockOnHand(), 0.001);
    }

    public function test_receive_purchase_creates_batches_and_marks_received(): void
    {
        $item = $this->item();
        $box = Unit::query()->where('abbreviation', 'box')->firstOrFail(); // 100 pc

        $purchase = Purchase::query()->create([
            'status' => PurchaseStatus::Draft,
            'ordered_at' => now(),
        ]);
        PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'inventory_item_id' => $item->id,
            'quantity' => 2,          // 2 boxes
            'unit_id' => $box->id,
            'unit_cost' => 300,       // per box
            'batch_number' => 'PO-1',
            'expiry_date' => now()->addYear(),
        ]);

        app(ReceivePurchase::class)->handle($purchase->fresh());

        $item->refresh();
        $this->assertEqualsWithDelta(200.0, $item->stockOnHand(), 0.001); // 2 boxes * 100
        $this->assertSame(PurchaseStatus::Received, $purchase->fresh()->status);
        $this->assertEqualsWithDelta(600.0, (float) $purchase->fresh()->total_cost, 0.001);
    }
}
