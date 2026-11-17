<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->string('currency', 3)->default('EUR')->after('email');
            $table->string('timezone', 64)->default('UTC')->after('currency');
            // null = every module enabled (see App\Domains\Core\Support\Modules).
            $table->json('modules')->nullable()->after('timezone');
            // Free-form per-center defaults (session prices, durations...) — see Center::setting().
            $table->json('settings')->nullable()->after('modules');
        });

        Schema::table('users', function (Blueprint $table) {
            // null = no per-user restriction (the center's modules apply as is).
            $table->json('modules')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->dropColumn(['currency', 'timezone', 'modules', 'settings']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
