<?php

namespace App\Domains\Common\Http\Requests;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnumOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EnumOption::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // enum_type is not a fixed list — new domains add new types by
            // seeding/creating options, not by migration (see EnumOption's
            // whole purpose). Free text here, not a select-from-existing.
            'enum_type' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('enum_options')->where('enum_type', $this->string('enum_type')),
            ],
            'label' => ['required', 'array'],
            'label.fr' => ['required', 'string', 'max:255'],
            'label.en' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
