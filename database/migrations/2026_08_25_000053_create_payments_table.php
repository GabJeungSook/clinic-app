<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMMUTABLE ledger of money received. Refunds are negative-amount rows.
     * Never updated or deleted.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('method'); // cash|card|transfer|ewallet|other
            $table->decimal('amount', 12, 2); // negative = refund
            $table->string('reference')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
