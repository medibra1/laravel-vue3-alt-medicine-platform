<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\Consent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document download route only (no thumbnail — a signed consent is
 * always a single-page PDF, no image conversion registered on Consent
 * unlike Patient's document collections).
 *
 * @mixin Consent
 */
class ConsentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'source' => $this->source,
            'version' => $this->version,
            // Only meaningful for source = 'digital' — an 'uploaded'
            // consent has no template to compare against, so it can
            // never be flagged "à renouveler" by version drift (see
            // PatientConsentsTab.vue's isUpToDate()).
            'template_version' => $this->template?->version,
            'signer_name' => $this->signer_name,
            'accepted_at' => $this->accepted_at,
            'accepted_by' => $this->acceptedBy->name,
            'download_url' => route('admin.patients.consents.show', [$this->patient_id, $this->id]),
        ];
    }
}
