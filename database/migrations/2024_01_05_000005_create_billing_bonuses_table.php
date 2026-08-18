<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained('practitioners')->cascadeOnDelete();
            $table->foreignId('pay_period_id')->nullable()->constrained('billing_pay_periods')->nullOnDelete(); // applicable to either payroll mode — nullable because a conventional-mode bonus may not be tied to a pool-sharing period at all
            $table->unsignedBigInteger('amount'); // cents
            $table->string('currency', 3);
            $table->text('reason')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('granted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_bonuses');
    }
};
