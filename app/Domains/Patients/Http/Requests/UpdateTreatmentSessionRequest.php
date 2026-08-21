<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\TreatmentSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTreatmentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TreatmentSession $session */
        $session = $this->route('session');

        return $this->user()->can('update', $session);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var TreatmentSession $session */
        $session = $this->route('session');

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
                Rule::exists('treatment_diseases', 'disease_id')->where('treatment_id', $session->treatment_id),
            ],
            'disease_progress.*.outcome' => ['nullable', Rule::in(['cured', 'not_cured', 'percentage', 'ongoing'])],
            'disease_progress.*.outcome_percentage' => ['nullable', 'integer', 'min:1', 'max:99'],
            'disease_progress.*.notes' => ['nullable', 'string'],
        ];
    }

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
