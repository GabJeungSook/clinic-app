<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Users keep their bigint id (auth/system table); they simply gain a
            // branch association plus staff profile fields.
            $table->foreignUlid('branch_id')->nullable()->after('id')
                ->constrained('branches')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('job_title');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['phone', 'job_title', 'is_active', 'last_login_at']);
        });
    }
};
