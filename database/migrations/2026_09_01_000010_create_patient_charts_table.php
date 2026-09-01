<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One clinical chart per patient — the digital version of the clinic's paper
 * "Waiver Form & Patient Chart". Fixed checklists and repeatable rows are kept
 * as JSON (this is a snapshot record edited in place, not queried by member),
 * while the two single-select fields (skin type, face shape) are plain strings
 * cast to enums in the model, mirroring how `patients.sex` is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_charts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('patient_id')->constrained('patients')->cascadeOnDelete();

            $table->json('history_flags')->nullable();          // "I have" / "I am taking" / "Current condition"
            $table->json('procedures_done')->nullable();        // botox/fillers/collagen/threads {done, when}
            $table->json('lifestyle')->nullable();              // sleep/eating/exercise + past medical / surgery
            $table->json('derma_history')->nullable();          // previous derma consult {had_consult, reason, when}
            $table->json('initial_plan')->nullable();           // checklist items + free "others"
            $table->json('physician_notes')->nullable();        // rows: observations/test_ordered/results/notes
            $table->json('assessment_conditions')->nullable();  // doctor's skin-condition checklist + "others"
            $table->json('beauty_plan')->nullable();            // rows: procedure/price/timeline

            $table->string('skin_type')->nullable();
            $table->string('face_shape')->nullable();
            $table->text('findings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_charts');
    }
};
