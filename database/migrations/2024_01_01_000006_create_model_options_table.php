<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_options', function (Blueprint $table) {
            $table->id();
            $table->morphs('model'); // model_type, model_id
            $table->foreignId('option_id')->constrained('enum_options')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['model_type', 'model_id', 'option_id'], 'model_options_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_options');
    }
};
