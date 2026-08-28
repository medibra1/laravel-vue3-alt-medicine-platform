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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['treatment', 'data_privacy', 'image_rights'])],
            'signer_name' => ['required', 'string', 'max:255'],
            'signature_svg' => ['nullable', 'string'],
            'accepted' => ['required', 'accepted'],
        ];
    }
}
