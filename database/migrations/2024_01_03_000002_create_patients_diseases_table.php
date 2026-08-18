<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients_diseases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disease_category_id')->constrained('patients_disease_categories')->cascadeOnDelete();
            $table->string('code', 3); // unique PER category, not globally (e.g. 101, 201, 301...)
            $table->json('label'); // translatable
            $table->json('description')->nullable(); // translatable
            $table->unsignedInteger('default_duration_months');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['disease_category_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients_diseases');
    }
};
