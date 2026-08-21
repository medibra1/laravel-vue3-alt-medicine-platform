<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treatment_diseases', function (Blueprint $table) {
            $table->foreignId('treatment_id')->constrained('treatments')->cascadeOnDelete();
            $table->foreignId('disease_id')->constrained('diseases')->cascadeOnDelete();
            $table->primary(['treatment_id', 'disease_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_diseases');
    }
};
