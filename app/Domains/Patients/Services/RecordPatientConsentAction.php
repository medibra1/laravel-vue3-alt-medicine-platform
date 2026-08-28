<?php

namespace App\Domains\Patients\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\Consent;
use App\Domains\Patients\Models\ConsentTemplate;
use App\Domains\Patients\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Records a patient's acceptance of the currently active template for a
 * given type, freezes the accepted text into content_snapshot (so a
 * later template edit — always a new version, see
 * ConsentTemplateController::update() — never rewrites what was
 * actually signed), and generates+attaches the PDF in one step. A
 * single write path, so the PDF and the DB row can never drift apart.
 */
class RecordPatientConsentAction
{
    /**
     * @param  array{signer_name: string, signature_svg: ?string}  $data
     */
    public function __invoke(Patient $patient, string $type, array $data, User $acceptedBy, ?string $ip): Consent
    {
        $template = ConsentTemplate::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        abort_if($template === null, 422, "No active consent template for type \"{$type}\".");

        $consent = $patient->consents()->create([
            'consent_template_id' => $template->id,
            'version' => $template->version,
            'content_snapshot' => $template->content,
            'signer_name' => $data['signer_name'],
            'signature_svg' => $data['signature_svg'] ?? null,
            'accepted_at' => now(),
            'accepted_by' => $acceptedBy->id,
            'ip_address' => $ip,
        ]);

        $pdf = Pdf::loadView('pdf.consent', [
            'template' => $template,
            'patient' => $patient,
            'consent' => $consent,
        ])->output();

        $tempPath = sys_get_temp_dir().'/'.Str::uuid().'.pdf';
        file_put_contents($tempPath, $pdf);

        $consent->addMedia($tempPath)
            ->usingFileName('consent-'.$consent->id.'.pdf')
            ->usingName($template->title.' — '.now()->translatedFormat('d F Y'))
            ->toMediaCollection('document');

        return $consent;
    }
}
