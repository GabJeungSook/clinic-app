<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-session cost of delivering a service (typed by staff). Lets the sales
 * report show gross vs net sales (revenue minus the cost of services rendered).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->default(0)->after('default_price');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
