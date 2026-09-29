<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->date('starts_on');
            // Inclusive, same convention as practitioner_time_offs.
            $table->date('ends_on');
            // Free text ("Fête nationale", "Travaux...") — closure reasons are
            // too varied for a closed list, unlike practitioner_time_offs.reason.
            $table->string('label');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['center_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_closures');
    }
};
