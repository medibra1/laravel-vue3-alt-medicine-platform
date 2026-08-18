<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payroll_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('organism_id')->constrained('billing_payroll_organisms')->cascadeOnDelete();
            $table->string('label'); // e.g. "Health insurance", "Retirement — employer share"
            $table->decimal('rate_percent', 5, 2); // applied to base_salary_amount — the actual calculation engine is not implemented yet
            $table->string('charge_type', 20); // employer | employee — kept as a plain string for now (2 fixed values, an enum cast can be added once the calculator is built)
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payroll_charges');
    }
};
