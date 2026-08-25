<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\DiseaseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiseaseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DiseaseCategory $diseaseCategory */
        $diseaseCategory = $this->route('disease_category');

        return $this->user()->can('update', $diseaseCategory);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var DiseaseCategory $diseaseCategory */
        $diseaseCategory = $this->route('disease_category');

        return [
            'type_option_id' => ['required', 'integer', 'exists:enum_options,id'],
            'code' => ['required', 'string', 'max:10', Rule::unique('disease_categories', 'code')->ignore($diseaseCategory)],
            'label.fr' => ['required', 'string', 'max:255'],
            'label.en' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
