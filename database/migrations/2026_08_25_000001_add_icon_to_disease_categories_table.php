<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disease_categories', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('label'); // mdi icon name, e.g. "mdi-stomach"
        });
    }

    public function down(): void
    {
        Schema::table('disease_categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
