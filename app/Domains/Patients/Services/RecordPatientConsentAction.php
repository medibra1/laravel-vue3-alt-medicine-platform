<?php

namespace App\Domains\Patients\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\Consent;
use App\Domains\Patients\Models\ConsentTemplate;
use App\Domains\Patients\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Records a patient's consent, either 'digital' (electronic signature
 * against the currently active template for the type — freezes the
 * accepted text into content_snapshot so a later template edit never
 * rewrites what was actually signed, generates+attaches a PDF) or
 * 'uploaded' (an already-signed paper document scanned/photographed
 * in — no template to snapshot, the uploaded file(s) become the
 * Consent's media directly). A single write path for both, so the DB
 * row and its attached document can never drift apart regardless of
 * source.
 */
class RecordPatientConsentAction
{
    /**
     * @param  array{signer_name: string, signature_svg: ?string}  $data
     */
    public function digital(Patient $patient, string $type, array $data, User $acceptedBy, ?string $ip): Consent
    {
        $template = ConsentTemplate::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->first();

        abort_if($template === null, 422, "No active consent template for type \"{$type}\".");

        $consent = $patient->consents()->create([
            'type' => $type,
            'source' => 'digital',
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

    /**
     * @param  array{signer_name: string, signature_svg: ?string, accepted_at: Carbon}  $data
     * @param  UploadedFile[]  $files
     */
    public function uploaded(Patient $patient, string $type, array $data, array $files, User $acceptedBy, ?string $ip, MergeImagesIntoPdfAction $mergeImages): Consent
    {
        $consent = $patient->consents()->create([
            'type' => $type,
            'source' => 'uploaded',
            'signer_name' => $data['signer_name'],
            'signature_svg' => $data['signature_svg'] ?? null,
            'accepted_at' => $data['accepted_at'],
            'accepted_by' => $acceptedBy->id,
            'ip_address' => $ip,
        ]);

        // Same merge-multiple-photos-into-one-pdf behavior already used
        // for patient documents (PatientDocumentController) — a single
        // upload attaches as-is, several photos of the same paper
        // document merge into one file rather than one Media per photo.
        if (count($files) > 1) {
            $mergedPath = $mergeImages($files);

            $consent->addMedia($mergedPath)
                ->usingFileName('consent-'.$consent->id.'.pdf')
                ->toMediaCollection('document');
        } else {
            $consent->addMedia($files[0])
                ->toMediaCollection('document');
        }

        return $consent;
    }
}
