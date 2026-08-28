<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        return $this->user()->can('confirm', $treatment);
    }

    /**
     * Real, full validation — this is where draft-stage leniency ends.
     * Required fields mirror docs/schema-donnees.md's non-nullable
     * columns for `treatments` plus at least one disease (a
     * confirmed treatment without any targeted disease is meaningless).
     *
     * care_item_ids carries the wizard's "Soins — 1ère séance" step —
     * optional, captured at confirmation time, stored as the treatment's
     * first (implicit) TreatmentSession rather than on the treatment
     * itself, so it uses the same storage path as every later real
     * session. Only has an effect on the treatment's very first
     * confirmation (see Treatment::sessions()->doesntExist() in
     * TreatmentController::confirm()) — disease-outcome tracking now
     * only ever happens through TreatmentSessionController, not here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'practitioner_id' => ['required', 'integer', 'exists:practitioners,id'],
            'started_at' => ['required', 'date'],
            'disease_ids' => ['required', 'array', 'min:1'],
            'disease_ids.*' => ['integer', 'exists:diseases,id'],
            // Must be a subset of disease_ids — Rule::in reads the sibling
            // field so a disease can't be marked actively tracked without
            // also being selected at all. Missing entirely defaults every
            // selected disease to actively tracked (see
            // TreatmentController::syncDiseases()).
            'actively_tracked_disease_ids' => ['sometimes', 'array'],
            'actively_tracked_disease_ids.*' => ['integer', Rule::in($this->input('disease_ids', []))],
            'outcome_percentage' => [Rule::requiredIf($this->input('outcome') === 'percentage'), 'nullable', 'integer', 'min:1', 'max:99'],
            'care_item_ids' => ['nullable', 'array'],
            'care_item_ids.*' => ['integer', 'exists:care_items,id'],
        ];
    }

    /**
     * Same safety net as ConfirmPatientRequest: validates the treatment's
     * persisted state (merged with any last-second payload), not just the
     * request body, in case the frontend hasn't flushed the last autosave
     * before the confirm click.
     *
     * Falls back with `??`, not `$this->input($key, $fallback)` (whose
     * default only applies when the key is missing entirely) — see the
     * identical fix/reasoning on ConfirmPatientRequest::prepareForValidation().
     * useResilientForm spreads the whole reactive form on every submit,
     * so an explicit null for a field the wizard hasn't (yet) filled
     * survived past the old array_filter-based merge and failed
     * 'required' below with a real validation error.
     */
    protected function prepareForValidation(): void
    {
        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        $this->merge([
            'practitioner_id' => $this->input('practitioner_id') ?? $treatment->practitioner_id,
            'started_at' => $this->input('started_at') ?? $treatment->started_at?->toDateString(),
            'outcome' => $this->input('outcome') ?? $treatment->outcome,
        ]);

        if (! $this->has('disease_ids')) {
            $this->merge(['disease_ids' => $treatment->diseases()->pluck('diseases.id')->all()]);
        }

        if (! $this->has('actively_tracked_disease_ids')) {
            $this->merge([
                'actively_tracked_disease_ids' => $treatment->diseases()
                    ->wherePivot('actively_tracked', true)
                    ->pluck('diseases.id')
                    ->all(),
            ]);
        }
    }
}
