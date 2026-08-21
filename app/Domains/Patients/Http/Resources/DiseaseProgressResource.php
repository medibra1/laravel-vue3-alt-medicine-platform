<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\TreatmentSessionDiseaseProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TreatmentSessionDiseaseProgress
 */
class DiseaseProgressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'disease_id' => $this->disease_id,
            'disease_label' => $this->whenLoaded('disease', fn () => $this->disease->label),
            'outcome' => $this->outcome,
            'outcome_percentage' => $this->outcome_percentage,
            'notes' => $this->notes,
        ];
    }
}
