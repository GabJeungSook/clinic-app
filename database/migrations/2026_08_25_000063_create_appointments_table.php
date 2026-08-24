<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bookings taken (e.g. a patient calls to reserve a service at a time).
     * patient_id is nullable so a new caller can be booked before registration,
     * with guest_name/guest_phone captured in the meantime.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->foreignUlid('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'scheduled_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
