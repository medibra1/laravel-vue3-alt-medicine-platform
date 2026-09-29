<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practitioner_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practitioner_id')->constrained('practitioners')->cascadeOnDelete();
            $table->date('starts_on');
            // Inclusive — a single-day time off has starts_on === ends_on.
            $table->date('ends_on');
            // vacation | sick_leave | training | other — plain string, same
            // choice as outcome/closure_reason/status elsewhere.
            $table->string('reason', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['practitioner_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practitioner_time_offs');
    }
};
