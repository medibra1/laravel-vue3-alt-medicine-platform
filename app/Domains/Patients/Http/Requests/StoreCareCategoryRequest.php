<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\CareCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CareCategory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Free-form English slug (e.g. 'ointment'/'bath'), not
            // auto-suggested — unlike CareItem::code, unique globally.
            'code' => ['required', 'string', 'max:255', Rule::unique('care_categories')],
            'label' => ['required', 'array'],
            'label.fr' => ['required', 'string', 'max:255'],
            'label.en' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
