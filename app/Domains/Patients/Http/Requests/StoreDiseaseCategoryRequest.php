<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\DiseaseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiseaseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DiseaseCategory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type_option_id' => ['required', 'integer', 'exists:enum_options,id'],
            'code' => ['required', 'string', 'max:10', Rule::unique('disease_categories', 'code')],
            'label.fr' => ['required', 'string', 'max:255'],
            'label.en' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
