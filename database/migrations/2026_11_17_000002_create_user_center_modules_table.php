<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modules an account may use on ONE center, set by that center's
        // manager. No row = no restriction on that center. Narrows, never
        // widens: the center's own modules and users.modules (the
        // account-wide restriction set by an admin) still apply.
        Schema::create('user_center_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->json('modules');
            $table->timestamps();
            $table->unique(['user_id', 'center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_center_modules');
    }
};
