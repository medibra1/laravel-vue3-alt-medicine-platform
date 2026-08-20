<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients_treatment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained('patients_treatments')->cascadeOnDelete();
            $table->foreignId('practitioner_id')->nullable()->constrained('practitioners')->nullOnDelete(); // may differ from the treatment's practitioner on reassignment
            $table->date('session_date')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients_treatment_sessions');
    }
};
