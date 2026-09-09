<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('practitioners')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained('treatments')->nullOnDelete();
            // Set only at conversion time (see TreatmentSessionController::store()) —
            // an appointment doesn't own a session until the patient actually
            // shows up and one is logged for it.
            $table->foreignId('treatment_session_id')->nullable()->unique()->constrained('treatment_sessions')->nullOnDelete();
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_minutes');
            // String status, not spatie/laravel-model-status — same choice
            // already made for Treatment.outcome/closure_reason: few values,
            // no need for a history trail on top of updated_at.
            $table->string('status', 20)->default('scheduled');
            $table->text('reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['practitioner_id', 'starts_at']);
            $table->index(['center_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
