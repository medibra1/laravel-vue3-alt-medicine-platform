<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Small closed set (single/married/divorced/widowed), same
            // string-literal choice already made for gender — not a PHP
            // enum, not enough surface to justify one.
            $table->string('marital_status', 20)->nullable()->after('gender');
            $table->unsignedTinyInteger('children_count')->nullable()->after('marital_status');
            // Open-ended list, editable in admin without a deploy — same
            // EnumOption mechanism already used for disease_category.type
            // etc., not a fixed PHP list (see docs/schema-donnees.md).
            $table->foreignId('religion_option_id')->nullable()->after('children_count')
                ->constrained('enum_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('religion_option_id');
            $table->dropColumn(['marital_status', 'children_count']);
        });
    }
};
