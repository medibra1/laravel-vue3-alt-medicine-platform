<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            // Default: pool_sharing — the simpler model, no fixed salary.
            // A center manager switches to 'conventional' if they need
            // real contracts and payroll charges. See PayrollMode enum.
            $table->string('payroll_mode', 20)->default('pool_sharing')->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->dropColumn('payroll_mode');
        });
    }
};
