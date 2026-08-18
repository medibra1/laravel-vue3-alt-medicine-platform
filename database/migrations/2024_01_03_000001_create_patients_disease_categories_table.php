<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients_disease_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_option_id')->constrained('enum_options')->cascadeOnDelete();
            $table->string('code')->unique(); // "1" to "8" in the source document, extensible
            $table->json('label'); // translatable
            $table->unsignedInteger('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients_disease_categories');
    }
};
