<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained('practitioners')->cascadeOnDelete();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->string('contract_type')->nullable(); // deliberately loose for now — contract types vary a lot by country, formalized when a real center adopts conventional payroll
            $table->unsignedBigInteger('base_salary_amount')->nullable(); // cents — null until the practitioner is actually paid a fixed base
            $table->string('currency', 3)->nullable();
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        // Status (active / terminated) via spatie/laravel-model-status.
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_employments');
    }
};
