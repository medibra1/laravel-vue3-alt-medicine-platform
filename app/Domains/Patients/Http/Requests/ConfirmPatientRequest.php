<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('confirm', $patient);
    }

    /**
     * Real, full validation — this is where draft-stage leniency ends.
     * Required fields mirror docs/schema-donnees.md's non-nullable
     * columns for `patients`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:255'],
            'intake_center_id' => ['required', 'integer', 'exists:centers,id'],
        ];
    }

    /**
     * The frontend flushes the pending autosave before calling confirm,
     * but as a safety net this validates the patient's persisted state
     * (merged with any last-second payload), not just the request body —
     * a click on "Confirmer" should never pass validation on stale data
     * that never actually made it to the database.
     *
     * Falls back to the persisted value whenever the payload's own value
     * is null/absent — not `$this->input($key, $fallback)` (its default
     * only kicks in when the key is missing entirely). A manager/
     * practitioner never sees an "Centre d'accueil" field at all
     * (PatientInfoForm.vue only renders it when `centers.length`), so
     * `form.intake_center_id` in the resilient form stays null forever
     * client-side — confirmPatient() still submits it as an explicit
     * `null` (useResilientForm spreads the whole reactive `form`), which
     * `input()`'s default never overrides. That null then survived into
     * the merged request and failed the 'required' rule below with a
     * genuine validation error — a 302 redirect-back that renders
     * exactly like "stuck on the form", found via real browser testing.
     */
    protected function prepareForValidation(): void
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        $this->merge([
            'first_name' => $this->input('first_name') ?? $patient->first_name,
            'last_name' => $this->input('last_name') ?? $patient->last_name,
            'gender' => $this->input('gender') ?? $patient->gender,
            'phone' => $this->input('phone') ?? $patient->phone,
            'city' => $this->input('city') ?? $patient->city,
            'intake_center_id' => $this->input('intake_center_id') ?? $patient->intake_center_id,
        ]);
    }
}
