<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_payroll_organisms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete(); // the collecting body differs per country (e.g. France's URSSAF has no equivalent structure elsewhere)
            $table->foreignId('type_option_id')->constrained('enum_options')->cascadeOnDelete(); // enum_type = payroll_organism.type: tax / social_security / pension / health_insurance / other — extensible per country without a migration
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payroll_organisms');
    }
};
