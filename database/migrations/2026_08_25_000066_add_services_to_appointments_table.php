<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Full list of booked services, each optionally drawing from a package:
            // [{ service_id, course_id|null }, …]. The existing service_id/course_id
            // columns stay as the "primary" (first) service for calendar/reports.
            $table->json('services')->nullable()->after('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('services');
        });
    }
};
