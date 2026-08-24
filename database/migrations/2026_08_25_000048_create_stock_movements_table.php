<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMMUTABLE ledger — the single source of truth for stock. Never updated or
     * soft-deleted; corrections are new reversing rows. On-hand for an item (or
     * batch) is SUM(quantity), where quantity is signed (+in / -out).
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUlid('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->string('type');
            // Stored in the item base unit, already signed by MovementType::sign().
            $table->decimal('quantity', 12, 3);
            $table->foreignUlid('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('unit_cost', 12, 4)->nullable();
            // Polymorphic link to the originating record (session, purchase, invoice…).
            $table->nullableUlidMorphs('reference');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('occurred_at');
            // Append-only: created_at only, no updated_at.
            $table->timestamp('created_at')->nullable();

            $table->index(['inventory_item_id', 'occurred_at']);
            $table->index(['batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
