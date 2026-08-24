<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The actual products used in a session — full trace from clinical act to
     * the inventory ledger via stock_movement_id.
     */
    public function up(): void
    {
        Schema::create('treatment_session_consumptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('treatment_session_id')->constrained('treatment_sessions')->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUlid('batch_id')->nullable()->constrained('batches')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->foreignUlid('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUlid('stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->index(['treatment_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_session_consumptions');
    }
};
