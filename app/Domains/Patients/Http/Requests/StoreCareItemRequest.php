<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\CareItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CareItem::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'care_category_id' => ['required', 'integer', 'exists:care_categories,id'],
            // Auto-suggested by CareItemCodeGenerator::suggestNext() on
            // the frontend, but this field stays editable.
            'code' => [
                'required',
                'digits:3',
                Rule::unique('care_items')->where('care_category_id', $this->integer('care_category_id')),
            ],
            'label' => ['required', 'array'],
            'label.fr' => ['required', 'string', 'max:255'],
            'label.en' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'description.fr' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
