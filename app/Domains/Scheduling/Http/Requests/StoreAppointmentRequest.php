<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Rules\NoAppointmentConflict;
use App\Domains\Scheduling\Rules\NoCenterClosureConflict;
use App\Domains\Scheduling\Rules\NoTimeOffConflict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Appointment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Separate branches, not 'prohibited' stacked with
            // 'integer'/'exists' in one array — a non-super_admin's form
            // has no center field at all, so center_id arrives as null,
            // which fails 'integer' before 'prohibited' is even
            // meaningfully evaluated (same class of bug already fixed on
            // StorePractitionerRequest/StorePatientDraftRequest's own
            // center field).
            'center_id' => $this->user()->isSuperAdmin()
                ? ['required', 'integer', 'exists:centers,id']
                : ['prohibited'],
            'patient_id' => [
                'required',
                'integer',
                Rule::exists('patients', 'id')->where('intake_center_id', $this->centerId()),
            ],
            // Visibility across the active center (including a real
            // multi-center practitioner) is checked in the controller via
            // Practitioner::visibleOnCenter() — there's no plain column
            // this rule could scope against on its own, same reasoning
            // already applied to practitioner selects elsewhere in this
            // domain.
            'practitioner_id' => ['required', 'integer', 'exists:practitioners,id'],
            'treatment_id' => ['nullable', 'integer', 'exists:treatments,id'],
            'starts_at' => ['required', 'date', new NoAppointmentConflict($this), new NoTimeOffConflict($this), new NoCenterClosureConflict($this)],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'modality' => ['required', Rule::in(['in_person', 'remote'])],
            // Not required even when modality is 'remote' — the
            // practitioner may add it later, this is a convenience field.
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'reason' => ['nullable', 'string'],
        ];
    }

    /**
     * A manager may not choose the center at all — it's forced to the
     * one EnsureCenterAccess resolved as active for them, same pattern
     * as StorePatientDraftRequest/StoreTreatmentDraftRequest. super_admin
     * has no active team of its own (see EnsureCenterAccess), so its
     * requests carry an explicit center_id field instead.
     */
    public function centerId(): ?int
    {
        return $this->user()->isSuperAdmin()
            ? $this->integer('center_id')
            : getPermissionsTeamId();
    }
}
