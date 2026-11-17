<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('center_care_item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('care_item_id')->constrained('care_items')->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->unique(['center_id', 'care_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('center_care_item_prices');
    }
};
