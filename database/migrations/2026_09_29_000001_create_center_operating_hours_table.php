<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opening hours of a center, per weekday — same shape as
     * practitioner_availabilities. A day with no row means the center is
     * closed that day.
     */
    public function up(): void
    {
        Schema::create('center_operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            // 0 = Sunday ... 6 = Saturday, same convention as practitioner_availabilities.
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['center_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_operating_hours');
    }
};
