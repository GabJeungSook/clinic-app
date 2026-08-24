<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // allergy|condition|medication|note|consent
            $table->string('title');
            $table->text('details')->nullable();
            // Non-queried structured extras (e.g. severity, reaction).
            $table->json('attributes')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_histories');
    }
};
