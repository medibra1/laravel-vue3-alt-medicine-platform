<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients_treatment_diseases', function (Blueprint $table) {
            $table->foreignId('treatment_id')->constrained('patients_treatments')->cascadeOnDelete();
            $table->foreignId('disease_id')->constrained('patients_diseases')->cascadeOnDelete();
            $table->primary(['treatment_id', 'disease_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients_treatment_diseases');
    }
};
