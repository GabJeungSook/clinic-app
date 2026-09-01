<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text notes for individual chart sections so the doctor can add remarks
 * alongside the structured checklists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_charts', function (Blueprint $table) {
            $table->text('procedures_notes')->nullable()->after('procedures_done');
            $table->text('lifestyle_notes')->nullable()->after('derma_history');
            $table->text('initial_plan_notes')->nullable()->after('initial_plan');
            $table->text('assessment_notes')->nullable()->after('assessment_conditions');
            $table->text('beauty_plan_notes')->nullable()->after('beauty_plan');
        });
    }

    public function down(): void
    {
        Schema::table('patient_charts', function (Blueprint $table) {
            $table->dropColumn(['procedures_notes', 'lifestyle_notes', 'initial_plan_notes', 'assessment_notes', 'beauty_plan_notes']);
        });
    }
};
