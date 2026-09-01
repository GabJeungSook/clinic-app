<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demographic fields from the paper chart's "Patient Information" block that
 * belong on the patient record itself rather than the clinical chart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('occupation')->nullable()->after('sex');
            $table->string('civil_status')->nullable()->after('occupation');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['occupation', 'civil_status']);
        });
    }
};
