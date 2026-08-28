<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_session_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_session_id')->constrained('treatment_sessions')->cascadeOnDelete();
            $table->foreignId('measurement_type_option_id')->constrained('enum_options')->cascadeOnDelete();
            $table->string('value'); // string, not numeric: covers "12/8" (blood pressure) as well as "0.95" (glucose)
            $table->string('unit')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['treatment_session_id', 'measurement_type_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_session_measurements');
    }
};
