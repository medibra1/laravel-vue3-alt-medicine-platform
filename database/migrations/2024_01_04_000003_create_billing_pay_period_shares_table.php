<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_pay_period_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_period_id')->constrained('billing_pay_periods')->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('practitioners')->cascadeOnDelete();

            // Snapshots at calculation time — a practitioner's grade/coefficient
            // can change later, but a settled share must stay reproducible and
            // auditable against what was actually used to compute it.
            $table->unsignedInteger('attendance_days');
            $table->decimal('grade_coefficient_snapshot', 5, 2);

            $table->unsignedBigInteger('gross_amount'); // cents, before advances are deducted
            $table->unsignedBigInteger('advances_deducted_amount')->default(0); // cents
            $table->unsignedBigInteger('net_amount'); // cents, gross - advances, never negative (see PayPeriodCalculator)

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['pay_period_id', 'practitioner_id']);
        });
        // Status (pending -> paid) via spatie/laravel-model-status.
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_pay_period_shares');
    }
};
