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
            // Separate branches, not 'prohibited' stacked with
            // 'integer'/'exists' in one array — a non-super_admin's form
            // has no center field at all, so intake_center_id arrives as
            // null, which fails 'integer' before 'prohibited' is even
            // meaningfully evaluated (same class of bug already fixed
            // on StorePractitionerRequest/StoreUserRequest's center_id —
            // this one had gone unnoticed because every prior browser
            // verification of this form happened as a manager who
            // already had at least one patient, never the very first
            // draft save with a non-super_admin session).
            'intake_center_id' => $this->user()->isSuperAdmin()
                ? ['required', 'integer', 'exists:centers,id']
                : ['prohibited'],
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

    /**
     * getPermissionsTeamId(), not User::managedCenterId() — the latter
     * only ever looks at a 'manager' role assignment, so it silently
     * returned null (Center::findOrFail() then 404s) for a practitioner
     * account, which has no 'manager' role at all. getPermissionsTeamId()
     * is the request's actual active center as EnsureCenterAccess
     * resolved it, correct for either role.
     */
    public function centerId(): ?int
    {
        return $this->user()->isSuperAdmin()
            ? $this->integer('intake_center_id')
            : getPermissionsTeamId();
    }
}
