<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
