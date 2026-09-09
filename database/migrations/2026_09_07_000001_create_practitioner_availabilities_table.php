<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioner_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained('practitioners')->cascadeOnDelete();
            // 0 = Sunday ... 6 = Saturday, matching Carbon::dayOfWeek.
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['practitioner_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_availabilities');
    }
};
