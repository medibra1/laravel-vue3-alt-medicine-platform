<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_category_id')->constrained('care_categories')->cascadeOnDelete();
            $table->string('code', 3); // unique PER category, not globally — same convention as diseases.code
            $table->json('label'); // translatable
            $table->json('description')->nullable(); // translatable
            $table->unsignedInteger('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['care_category_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_items');
    }
};
