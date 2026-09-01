<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text medical record. The clinic types the whole medical history as one
 * block rather than structured entries, so this is a single large text field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_charts', function (Blueprint $table) {
            $table->text('medical_record')->nullable()->after('findings');
        });
    }

    public function down(): void
    {
        Schema::table('patient_charts', function (Blueprint $table) {
            $table->dropColumn('medical_record');
        });
    }
};
