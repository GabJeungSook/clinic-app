<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('receipt_no');
            $table->timestamp('printed_at')->nullable();
            // Frozen totals/line snapshot for a stable printed document.
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'receipt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
