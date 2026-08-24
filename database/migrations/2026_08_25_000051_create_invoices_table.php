<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('invoice_no');
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0); // cache of payments sum
            // Tax settings SNAPSHOT at issue time so toggling the global setting
            // never rewrites historical invoices/receipts.
            $table->boolean('tax_enabled')->default(false);
            $table->decimal('tax_rate_snapshot', 6, 3)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'invoice_no']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
