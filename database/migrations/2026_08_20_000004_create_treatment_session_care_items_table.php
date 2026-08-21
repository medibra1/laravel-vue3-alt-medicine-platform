<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_session_care_items', function (Blueprint $table) {
            $table->foreignId('treatment_session_id')->constrained('treatment_sessions')->cascadeOnDelete();
            $table->foreignId('care_item_id')->constrained('care_items')->cascadeOnDelete();
            $table->primary(['treatment_session_id', 'care_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_session_care_items');
    }
};
