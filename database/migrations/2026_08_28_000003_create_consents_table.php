<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('consent_template_id')->constrained('consent_templates')->restrictOnDelete();
            $table->unsignedInteger('version'); // copy of consent_templates.version at the time of recording
            $table->longText('content_snapshot'); // frozen copy of the accepted text, independent from later template edits
            $table->string('signer_name');
            $table->longText('signature_svg')->nullable();
            $table->timestamp('accepted_at');
            $table->foreignId('accepted_by')->constrained('users')->cascadeOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
