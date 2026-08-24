<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single dated visit/session. Only status=completed counts against the
     * course total. scheduled_at is the hook for future online booking.
     */
    public function up(): void
    {
        Schema::create('treatment_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            // Nullable = ad-hoc single service not tied to a multi-session course.
            $table->foreignUlid('treatment_course_id')->nullable()
                ->constrained('treatment_courses')->cascadeOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('session_number')->nullable();
            $table->string('status')->default('scheduled'); // scheduled|completed|no_show|cancelled
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->string('before_photo_path')->nullable();
            $table->string('after_photo_path')->nullable();
            $table->timestamps();

            $table->index(['treatment_course_id', 'status']);
            $table->index(['patient_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_sessions');
    }
};
