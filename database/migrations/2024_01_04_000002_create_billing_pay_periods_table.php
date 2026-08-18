<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_pay_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->date('starts_at');
            $table->date('ends_at'); // free-form length — 3 days, a week, a month... the manager decides per period, no fixed cadence
            $table->unsignedBigInteger('pool_amount'); // amount to share among practitioners, in cents
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        // Status (draft -> calculated -> finalized -> paid) via spatie/laravel-model-status.
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_pay_periods');
    }
};
