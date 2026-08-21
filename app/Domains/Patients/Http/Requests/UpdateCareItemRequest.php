<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\CareItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCareItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var CareItem $careItem */
        $careItem = $this->route('care_item');

        return $this->user()->can('update', $careItem);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CareItem $careItem */
        $careItem = $this->route('care_item');

        return [
            'care_category_id' => ['required', 'integer', 'exists:care_categories,id'],
            'code' => [
                'required',
                'digits:3',
                Rule::unique('care_items')->where('care_category_id', $this->integer('care_category_id'))->ignore($careItem),
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
