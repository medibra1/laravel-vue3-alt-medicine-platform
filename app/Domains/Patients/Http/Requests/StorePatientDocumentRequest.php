<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('update', $patient);
    }

    /**
     * HEIC intentionally not accepted yet — Imagick on this environment
     * doesn't reliably rasterize it without libheif, and merging relies on
     * Imagick. jpg/png/pdf only until that's revisited.
     *
     * treatment_session_id is optional and only meaningful for the
     * 'medical' collection — a medical document uploaded from
     * TreatmentSessionDialog.vue is tagged with the session it was
     * captured during (custom_properties, not a pivot table: still the
     * same single medical collection on Patient, just annotated). Scoped
     * to sessions belonging to this patient's own treatments, not just
     * "any session" — otherwise a forged id could tag a document against
     * an unrelated patient's session.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'collection' => ['required', Rule::in(['identity', 'medical', 'other'])],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:20480'],
            'treatment_session_id' => [
                'nullable',
                'integer',
                Rule::exists('treatment_sessions', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'treatment_id',
                        $patient->treatments()->select('id')
                    )
                ),
            ],
        ];
    }
}
