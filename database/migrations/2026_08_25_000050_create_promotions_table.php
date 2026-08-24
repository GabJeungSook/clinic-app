<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type'); // percent|fixed
            $table->decimal('value', 12, 2);
            $table->string('applies_to')->default('invoice'); // invoice|service|item|category
            // Optional target (service/item/category id) when scoped.
            $table->ulid('target_id')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->decimal('min_spend', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
