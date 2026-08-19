<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Patient::class);
    }

    /**
     * Deliberately relaxed: a draft autosave must never fail because a
     * field is merely absent — presence validation is deferred to
     * ConfirmPatientRequest. Only client_uuid (idempotency key) and
     * intake_center_id (scoping) are actually required here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'intake_center_id' => [$this->user()->isSuperAdmin() ? 'required' : 'prohibited', 'integer', 'exists:centers,id'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::in(['male', 'female'])],
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

    public function centerId(): ?int
    {
        return $this->user()->isSuperAdmin()
            ? $this->integer('intake_center_id')
            : $this->user()->managedCenterId();
    }
}
