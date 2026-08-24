<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('abbreviation');
            // Self-reference: e.g. "box" -> base "piece" with factor 100.
            $table->foreignUlid('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('factor_to_base', 16, 6)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
