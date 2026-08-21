<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Core\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Country $country */
        $country = $this->route('country');

        return $this->user()->can('update', $country);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Country $country */
        $country = $this->route('country');

        return [
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            'code' => ['required', 'digits:2', Rule::unique('countries', 'code')->ignore($country)],
            'name' => ['required', 'array'],
            'name.fr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
