<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained('practitioners')->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // cents
            $table->string('currency', 3);
            $table->date('granted_at');
            $table->text('reason')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();

            // Set once this advance has actually been offset against a real
            // payout — null means "still outstanding, to be deducted from a
            // future pay period share".
            $table->foreignId('pay_period_share_id')->nullable()
                ->constrained('billing_pay_period_shares')->nullOnDelete();

            $table->timestamps();
        });
        // Status (pending -> approved -> deducted / cancelled) via spatie/laravel-model-status.
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_salary_advances');
    }
};
