<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A purchased plan of one service for one patient. Owns "sessions remaining",
     * which is derived from completed treatment_sessions (no stored counter).
     * Service name/price are snapshotted so later catalog edits don't rewrite
     * a patient's purchase history.
     */
    public function up(): void
    {
        Schema::create('treatment_courses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            $table->foreignUlid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('name_snapshot');
            $table->decimal('price_snapshot', 12, 2)->default(0);
            $table->unsignedInteger('total_sessions')->default(1);
            $table->string('status')->default('active'); // active|completed|cancelled|expired
            $table->timestamp('purchased_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_courses');
    }
};
