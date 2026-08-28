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
            // type lives directly on Consent, not resolved through the
            // template — required regardless of source, since an
            // 'uploaded' consent has no template to fall back on and the
            // per-type list in the Consentement tab needs it either way.
            $table->string('type');
            // 'digital' (electronic signature, this table's original
            // shape) | 'uploaded' (an already-signed paper document
            // scanned/photographed in). Free string, not a PHP enum — two
            // values, same reasoning already applied to outcome/closure_reason.
            $table->string('source');
            // Nullable: an 'uploaded' consent has no corresponding
            // template — the paper doesn't necessarily match any
            // template version word for word.
            $table->foreignId('consent_template_id')->nullable()->constrained('consent_templates')->restrictOnDelete();
            $table->unsignedInteger('version')->nullable(); // copy of consent_templates.version at the time of recording — null for 'uploaded'
            $table->longText('content_snapshot')->nullable(); // frozen copy of the accepted text — null for 'uploaded'
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
