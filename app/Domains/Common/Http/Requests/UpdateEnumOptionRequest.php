<?php

namespace App\Domains\Common\Http\Requests;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEnumOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EnumOption $enumOption */
        $enumOption = $this->route('enum_option');

        return $this->user()->can('update', $enumOption);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var EnumOption $enumOption */
        $enumOption = $this->route('enum_option');

        return [
            'enum_type' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('enum_options')->where('enum_type', $this->string('enum_type'))->ignore($enumOption),
            ],
            'label' => ['required', 'array'],
            'label.fr' => ['required', 'string', 'max:255'],
            'label.en' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
