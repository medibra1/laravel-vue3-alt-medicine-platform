<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients_disease_subcases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_id')->constrained('patients_diseases')->cascadeOnDelete();
            $table->json('label'); // translatable
            $table->json('description')->nullable(); // translatable
            $table->unsignedInteger('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients_disease_subcases');
    }
};
