<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTreatmentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Treatment::class);
    }

    /**
     * Deliberately relaxed, same philosophy as StorePatientDraftRequest:
     * only client_uuid (idempotency), patient_id (the treatment must
     * belong to a real patient from its very first save — there's no
     * meaningful draft without one) and center_id (scoping) are actually
     * required here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            // Separate branches, not 'prohibited' stacked with
            // 'integer'/'exists' — see the identical fix/reasoning on
            // StorePatientDraftRequest::rules() for center_id.
            'center_id' => $this->user()->isSuperAdmin()
                ? ['required', 'integer', 'exists:centers,id']
                : ['prohibited'],
            'practitioner_id' => ['sometimes', 'nullable', 'integer', 'exists:practitioners,id'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:started_at'],
            'outcome' => ['sometimes', 'nullable', Rule::in(['cured', 'not_cured', 'percentage'])],
            'outcome_percentage' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'disease_ids' => ['sometimes', 'nullable', 'array'],
            'disease_ids.*' => ['integer', 'exists:diseases,id'],
            // Must be a subset of disease_ids — same rule as
            // ConfirmTreatmentRequest/UpdateTreatmentDraftRequest. A
            // brand-new treatment has no persisted diseases yet, so unlike
            // the update request there's no "fall back to what's already
            // there" case to handle here.
            'actively_tracked_disease_ids' => ['sometimes', 'nullable', 'array'],
            'actively_tracked_disease_ids.*' => ['integer', Rule::in($this->input('disease_ids', []))],
        ];
    }

    /**
     * getPermissionsTeamId(), not User::managedCenterId() — see the same
     * fix/reasoning on StorePatientDraftRequest::centerId(). Equivalent
     * for a manager (EnsureCenterAccess sets the active team to exactly
     * managedCenterId() for them), but also correct if this is ever
     * reached by a role other than manager.
     */
    public function centerId(): ?int
    {
        return $this->user()->isSuperAdmin()
            ? $this->integer('center_id')
            : getPermissionsTeamId();
    }

    /**
     * The confusion this guards against: a raqi meaning to log a
     * session/appointment instead creates a whole new Treatment for a
     * patient who already has one in progress. Skipped when this
     * client_uuid already belongs to an existing treatment — that's just
     * the idempotent-retry path (storeDraft() returning the existing row
     * unchanged), not a genuinely new treatment, so it must never be
     * blocked by a status that changed on some *other* treatment for the
     * same patient in between retries.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $isRetryOfExisting = Treatment::query()
                ->where('client_uuid', $this->input('client_uuid'))
                ->exists();

            $patientId = $this->input('patient_id');

            if ($isRetryOfExisting || ! $patientId) {
                return;
            }

            $hasOngoingTreatment = Treatment::query()
                ->where('patient_id', $patientId)
                ->get()
                ->contains(fn (Treatment $treatment) => $treatment->currentStatusName() === 'ongoing');

            if ($hasOngoingTreatment) {
                $validator->errors()->add(
                    'patient_id',
                    'Ce patient a déjà un traitement en cours — fermez-le avant d\'en ajouter un nouveau.'
                );
            }
        });
    }
}
