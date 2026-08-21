<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Core\Enums\PayrollMode;
use App\Domains\Core\Models\Center;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Center $center */
        $center = $this->route('center');

        return $this->user()->can('update', $center);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Center $center */
        $center = $this->route('center');

        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'code' => [
                'required',
                'digits:2',
                Rule::unique('centers')->where('country_id', $this->integer('country_id'))->ignore($center),
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
