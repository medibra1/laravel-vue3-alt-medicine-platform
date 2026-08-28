<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTreatmentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TreatmentSession::class);
    }

    /**
     * Plain CRUD, not the resilient-wizard draft/confirm dance — a
     * session is a short, single-sitting log entry (what happened during
     * one appointment), not a long autosaved form. Real validation
     * applies immediately.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        return [
            'practitioner_id' => ['nullable', 'integer', 'exists:practitioners,id'],
            'session_date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'care_item_ids' => ['nullable', 'array'],
            'care_item_ids.*' => ['integer', 'exists:care_items,id'],
            'disease_progress' => ['nullable', 'array'],
            'disease_progress.*.disease_id' => [
                'required',
                'integer',
                Rule::exists('treatment_diseases', 'disease_id')->where('treatment_id', $treatment->id),
            ],
            'disease_progress.*.outcome' => ['nullable', Rule::in(['cured', 'not_cured', 'percentage', 'ongoing'])],
            'disease_progress.*.outcome_percentage' => ['nullable', 'integer', 'min:1', 'max:99'],
            'disease_progress.*.notes' => ['nullable', 'string'],
            'measurements' => ['nullable', 'array'],
            'measurements.*.measurement_type_option_id' => ['required', 'integer', 'exists:enum_options,id'],
            'measurements.*.value' => ['required', 'string', 'max:50'],
            'measurements.*.unit' => ['nullable', 'string', 'max:20'],
            'measurements.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * outcome_percentage is required only for the specific row whose
     * outcome is 'percentage' — a plain Rule::requiredIf on the whole
     * array can't express a per-row condition, so it's enforced here
     * instead.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('disease_progress', []) as $index => $row) {
                if (($row['outcome'] ?? null) === 'percentage' && ($row['outcome_percentage'] ?? null) === null) {
                    $validator->errors()->add(
                        "disease_progress.{$index}.outcome_percentage",
                        'The outcome percentage field is required when outcome is percentage.'
                    );
                }
            }
        });
    }
}
