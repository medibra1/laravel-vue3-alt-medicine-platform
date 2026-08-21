<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Core\Enums\PayrollMode;
use App\Domains\Core\Models\Center;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Center::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            // Auto-suggested by CenterCodeGenerator::suggestNext() on the
            // frontend, but this field stays editable — some countries
            // already hand out their own center numbers.
            'code' => [
                'required',
                'digits:2',
                Rule::unique('centers')->where('country_id', $this->integer('country_id')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'payroll_mode' => ['sometimes', new Enum(PayrollMode::class)],
        ];
    }
}
