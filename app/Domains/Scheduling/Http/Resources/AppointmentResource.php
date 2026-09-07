<?php

namespace App\Domains\Scheduling\Http\Resources;

use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'center_id' => $this->center_id,
            'practitioner_id' => $this->practitioner_id,
            'patient_id' => $this->patient_id,
            'treatment_id' => $this->treatment_id,
            'treatment_session_id' => $this->treatment_session_id,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->status,
            'reason' => $this->reason,
            'cancellation_reason' => $this->cancellation_reason,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'first_name' => $this->patient->first_name,
                'last_name' => $this->patient->last_name,
            ]),
            'practitioner' => $this->whenLoaded('practitioner', fn () => [
                'id' => $this->practitioner->id,
                'first_name' => $this->practitioner->first_name,
                'last_name' => $this->practitioner->last_name,
                'full_code' => $this->practitioner->full_code,
            ]),
        ];
    }
}
