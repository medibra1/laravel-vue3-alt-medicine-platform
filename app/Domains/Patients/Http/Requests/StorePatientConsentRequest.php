<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('update', $patient);
    }

    /**
     * signer_name/accepted/type stay required regardless of source —
     * that's what keeps a consent traceable and consistent with the
     * per-type list in the Consentement tab either way. 'accepted_at' is
     * only meaningful (and asked in the UI) for 'uploaded': a digital
     * consent's acceptance moment is 'now' by construction, but an
     * uploaded paper was signed on a real prior date that matters for
     * its legal/traceability value — before_or_equal:today, a signature
     * can't predate the app importing it into the future.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['treatment', 'data_privacy', 'image_rights'])],
            'source' => ['required', Rule::in(['digital', 'uploaded'])],
            'signer_name' => ['required', 'string', 'max:255'],
            'signature_svg' => ['nullable', 'string'],
            'accepted' => ['required', 'accepted'],
            'accepted_at' => [
                Rule::requiredIf($this->input('source') === 'uploaded'),
                'nullable',
                'date',
                'before_or_equal:today',
            ],
            'files' => [Rule::requiredIf($this->input('source') === 'uploaded'), 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:20480'],
        ];
    }
}
