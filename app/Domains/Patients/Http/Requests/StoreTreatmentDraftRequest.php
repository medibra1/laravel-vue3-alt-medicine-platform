<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'center_id' => [$this->user()->isSuperAdmin() ? 'required' : 'prohibited', 'integer', 'exists:centers,id'],
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

    public function centerId(): ?int
    {
        return $this->user()->isSuperAdmin()
            ? $this->integer('center_id')
            : $this->user()->managedCenterId();
    }
}
