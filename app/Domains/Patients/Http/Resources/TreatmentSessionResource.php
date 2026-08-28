<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\TreatmentSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TreatmentSession
 */
class TreatmentSessionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_date' => $this->session_date,
            'duration_minutes' => $this->duration_minutes,
            'notes' => $this->notes,
            'care_items' => CareItemResource::collection($this->whenLoaded('careItems')),
            'disease_progress' => DiseaseProgressResource::collection($this->whenLoaded('diseaseProgress')),
            'measurements' => TreatmentSessionMeasurementResource::collection($this->whenLoaded('measurements')),
        ];
    }
}
