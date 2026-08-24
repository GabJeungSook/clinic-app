<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            // Polymorphic source: service, treatment_course, inventory_item, or
            // null for a manual/free-text line.
            $table->nullableUlidMorphs('itemable');
            $table->string('description_snapshot');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->foreignUlid('promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->json('promotion_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
