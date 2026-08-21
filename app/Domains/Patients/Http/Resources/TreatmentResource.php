<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\Treatment;
use App\Domains\Practitioners\Http\Resources\PractitionerOptionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Summary shape for a treatment nested under a patient's file
 * (Admin/Patients/Form.vue "treatments" section) — display fields plus
 * the raw scalars (`practitioner_id`, `center_id`, `outcome`...) that
 * `editTreatment()` needs to prefill the wizard when re-opening an
 * existing treatment from that same page. The standalone
 * `/admin/treatments/{id}/edit` route still reads the model's raw
 * `toArray()` directly (see `TreatmentController::edit()`) rather than
 * through this resource — both end up sending the same fields, just via
 * different call sites that were never unified.
 *
 * @mixin Treatment
 */
class TreatmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'practitioner_id' => $this->practitioner_id,
            'center_id' => $this->center_id,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'outcome' => $this->outcome,
            'outcome_percentage' => $this->outcome_percentage,
            'notes' => $this->notes,
            'status' => $this->currentStatusName(),
            'closure_reason' => $this->closure_reason,
            // Plain null-check, not `new PractitionerOptionResource($this->whenLoaded(...))`:
            // a belongsTo relation loaded-but-empty resolves to null, and
            // wrapping null in a Resource throws when toArray() dereferences it.
            'practitioner' => $this->whenLoaded('practitioner', fn () => $this->practitioner ? new PractitionerOptionResource($this->practitioner) : null),
            'diseases' => DiseaseResource::collection($this->whenLoaded('diseases')),
            'sessions' => TreatmentSessionResource::collection($this->whenLoaded('sessions')),
        ];
    }
}
