<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bill of Materials: the default consumables a service uses, which drives
     * automatic stock deduction when a session is completed.
     */
    public function up(): void
    {
        Schema::create('service_consumables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignUlid('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->foreignUlid('unit_id')->constrained('units')->restrictOnDelete();
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->unique(['service_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_consumables');
    }
};
