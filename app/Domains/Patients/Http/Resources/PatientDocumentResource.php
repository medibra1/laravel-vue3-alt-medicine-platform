<?php

namespace App\Domains\Patients\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Media lives on the private 'local' disk (see Patient::registerMediaCollections())
 * — getUrl() doesn't produce a working link for a private disk, so both the
 * download and the thumbnail route through authenticated
 * PatientDocumentController routes rather than a direct storage URL.
 *
 * @mixin Media
 */
class PatientDocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'download_url' => route('admin.patients.documents.show', [$this->model_id, $this->id]),
            'thumb_url' => $this->hasGeneratedConversion('thumb')
                ? route('admin.patients.documents.thumb', [$this->model_id, $this->id])
                : null,
            'treatment_session_id' => $this->getCustomProperty('treatment_session_id'),
            'created_at' => $this->created_at,
        ];
    }
}
