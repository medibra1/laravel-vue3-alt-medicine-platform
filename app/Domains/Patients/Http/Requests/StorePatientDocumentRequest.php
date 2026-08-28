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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'collection' => ['required', Rule::in(['identity', 'medical', 'other'])],
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:20480'],
        ];
    }
}
