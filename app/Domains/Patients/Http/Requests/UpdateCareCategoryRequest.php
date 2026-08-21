<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\CareCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCareCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CareCategory $careCategory */
        $careCategory = $this->route('care_category');

        return $this->user()->can('update', $careCategory);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CareCategory $careCategory */
        $careCategory = $this->route('care_category');

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('care_categories')->ignore($careCategory)],
            'label' => ['required', 'array'],
            'label.fr' => ['required', 'string', 'max:255'],
            'label.en' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
