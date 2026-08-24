<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lots of a batch-tracked item. FEFO consumption draws from the batch with
     * the nearest expiry first. Remaining qty = sum of this batch's movements
     * (cached in qty_remaining_cache for fast reads).
     */
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignUlid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUlid('purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('initial_quantity', 12, 3)->default(0); // in item base unit
            $table->decimal('qty_remaining_cache', 12, 3)->default(0);
            $table->timestamps();

            $table->index(['inventory_item_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
