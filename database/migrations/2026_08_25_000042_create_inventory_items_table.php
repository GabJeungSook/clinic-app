<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('inventory_category_id')->nullable()
                ->constrained('inventory_categories')->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('type'); // consumable|product|retail|medication
            $table->foreignUlid('base_unit_id')->constrained('units')->restrictOnDelete();
            $table->boolean('is_batch_tracked')->default(true);
            $table->boolean('track_expiry')->default(true);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->decimal('reorder_qty', 12, 3)->default(0);
            $table->decimal('default_sell_price', 12, 2)->default(0);
            // Denormalised cache of on-hand qty (base unit); truth = stock_movements sum.
            $table->decimal('stock_on_hand_cache', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'sku']);
            $table->index(['branch_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
