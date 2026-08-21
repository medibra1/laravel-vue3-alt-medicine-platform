<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique(); // set by the resilient-wizard frontend on first save, makes storeDraft idempotent
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('intake_center_id')->constrained('centers')->cascadeOnDelete();
            $table->string('patient_number', 4)->nullable(); // auto-generated, next number per intake_center_id — see PatientNumberGenerator
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['intake_center_id', 'patient_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
