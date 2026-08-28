<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\TreatmentSessionMeasurement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TreatmentSessionMeasurement
 */
class TreatmentSessionMeasurementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'measurement_type_option_id' => $this->measurement_type_option_id,
            'measurement_type_code' => $this->whenLoaded('measurementType', fn () => $this->measurementType->code),
            // EnumOption.label is a plain `array` cast, not
            // spatie/laravel-translatable (unlike Disease/DiseaseCategory) —
            // resolve the French label explicitly, same as
            // PatientController::religionOptions().
            'measurement_type_label' => $this->whenLoaded('measurementType', fn () => $this->measurementType->label['fr'] ?? $this->measurementType->code),
            'value' => $this->value,
            'unit' => $this->unit,
            'notes' => $this->notes,
        ];
    }
}
