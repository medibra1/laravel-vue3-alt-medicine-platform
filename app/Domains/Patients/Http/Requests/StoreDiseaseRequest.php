<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Disease;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiseaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Disease::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'disease_category_id' => ['required', 'integer', 'exists:disease_categories,id'],
            // Auto-suggested by DiseaseCodeGenerator::suggestNext() on the
            // frontend, but this field stays editable.
            'code' => [
                'required',
                'string',
                'max:3',
                Rule::unique('diseases')->where('disease_category_id', $this->integer('disease_category_id')),
            ],
            'label.fr' => ['required', 'string', 'max:255'],
            'label.en' => ['required', 'string', 'max:255'],
            'description.fr' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'default_duration_months' => ['required', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
