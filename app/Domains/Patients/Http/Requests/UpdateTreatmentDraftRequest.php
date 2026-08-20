<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'disease_ids.*' => ['integer', 'exists:patients_diseases,id'],
        ];
    }
}
