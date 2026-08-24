<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('service_category_id')->nullable()
                ->constrained('service_categories')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            // 1 = single visit; N = multi-session course.
            $table->unsignedInteger('default_session_count')->default(1);
            $table->decimal('default_price', 12, 2)->default(0);
            $table->unsignedInteger('default_interval_days')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
