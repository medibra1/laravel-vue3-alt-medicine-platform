<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients_treatments', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique(); // resilient-wizard idempotency key, same pattern as patients.client_uuid
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('practitioner_id')->nullable()->constrained('practitioners')->nullOnDelete();
            $table->foreignId('center_id')->nullable()->constrained('centers')->nullOnDelete();
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->string('outcome', 20)->nullable(); // enum: cured/not_cured/percentage
            $table->unsignedTinyInteger('outcome_percentage')->nullable(); // 1-99, only meaningful when outcome = percentage
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients_treatments');
    }
};
