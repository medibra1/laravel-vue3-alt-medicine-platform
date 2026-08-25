<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTreatmentDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        return $this->user()->can('update', $treatment);
    }

    /**
     * Same relaxed philosophy as UpdatePatientDraftRequest — no presence
     * validation while still a draft. patient_id/center_id are not
     * editable here (a treatment's patient and intake center aren't
     * transferable once set, same call as Patient's intake_center_id).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'practitioner_id' => ['sometimes', 'nullable', 'integer', 'exists:practitioners,id'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:started_at'],
            'outcome' => ['sometimes', 'nullable', Rule::in(['cured', 'not_cured', 'percentage'])],
            'outcome_percentage' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'disease_ids' => ['sometimes', 'nullable', 'array'],
            'disease_ids.*' => ['integer', 'exists:diseases,id'],
            // Must be a subset of disease_ids (or of the treatment's already
            // persisted diseases, when disease_ids itself isn't part of this
            // particular save) — see ConfirmTreatmentRequest for the same rule.
            'actively_tracked_disease_ids' => ['sometimes', 'nullable', 'array'],
            'actively_tracked_disease_ids.*' => ['integer', Rule::in($this->activelyTrackableIds())],
        ];
    }

    /**
     * Once a treatment has at least one real session, a disease that
     * already has tracked progress can no longer be removed from
     * disease_ids — its treatment_session_disease_progress rows would
     * become orphaned (treatment_diseases' cascadeOnDelete only cleans up
     * the pivot row, not the progress history). Adding a new disease stays
     * free even after sessions exist — that's pure scope extension, no
     * data loss risk. Skipped entirely on a treatment with no sessions
     * yet: nothing has been tracked, so disease_ids stays freely editable
     * (unchanged behaviour for the plain wizard-drafting phase).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! array_key_exists('disease_ids', $this->validated())) {
                return;
            }

            /** @var Treatment $treatment */
            $treatment = $this->route('treatment');

            if ($treatment->sessions()->doesntExist()) {
                return;
            }

            $submittedIds = collect($this->input('disease_ids', []));
            $lockedIds = $this->lockedDiseaseIds($treatment);
            $removedLockedIds = $lockedIds->diff($submittedIds);

            if ($removedLockedIds->isNotEmpty()) {
                $validator->errors()->add(
                    'disease_ids',
                    'Cette maladie a déjà un suivi enregistré et ne peut plus être retirée.'
                );
            }
        });
    }

    /**
     * @return Collection<int, int>
     */
    protected function lockedDiseaseIds(Treatment $treatment): Collection
    {
        return $treatment->latestOutcomePerDisease()->keys();
    }

    /**
     * The set actively_tracked_disease_ids must be a subset of — the
     * submitted disease_ids when this save also touches the selection
     * itself, otherwise the treatment's already-persisted diseases (a
     * standalone actively_tracked toggle, which the wizard always sends
     * alongside disease_ids anyway, but this stays correct even if that
     * ever changes).
     *
     * @return array<int>
     */
    protected function activelyTrackableIds(): array
    {
        if ($this->has('disease_ids')) {
            return $this->input('disease_ids', []);
        }

        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        return $treatment->diseases()->pluck('diseases.id')->all();
    }
}
