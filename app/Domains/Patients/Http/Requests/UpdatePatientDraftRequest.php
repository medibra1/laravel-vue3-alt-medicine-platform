<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('update', $patient);
    }

    /**
     * Same relaxed philosophy as StorePatientDraftRequest — no
     * presence validation while still a draft. intake_center_id is not
     * editable here (a patient's intake center isn't transferable once
     * set, same call as UpdatePractitionerRequest not allowing center_id
     * edits).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
            'marital_status' => ['sometimes', 'nullable', Rule::in(['single', 'married', 'divorced', 'widowed'])],
            'children_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'religion_option_id' => ['sometimes', 'nullable', 'integer', 'exists:enum_options,id'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
