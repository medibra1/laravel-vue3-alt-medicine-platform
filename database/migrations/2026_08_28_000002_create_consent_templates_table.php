<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'treatment' | 'data_privacy' | 'image_rights'
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('content'); // text shown to the patient before signing
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_templates');
    }
};
