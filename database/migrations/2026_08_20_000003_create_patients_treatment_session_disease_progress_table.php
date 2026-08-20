<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients_treatment_session_disease_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_session_id')->constrained('patients_treatment_sessions')->cascadeOnDelete();
            $table->foreignId('disease_id')->constrained('patients_diseases')->cascadeOnDelete();
            $table->string('outcome', 20)->nullable(); // enum: cured/not_cured/percentage/ongoing
            $table->unsignedTinyInteger('outcome_percentage')->nullable(); // 1-99, only meaningful when outcome = percentage
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['treatment_session_id', 'disease_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients_treatment_session_disease_progress');
    }
};
