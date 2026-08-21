<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Core\Models\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Country::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
            // Not auto-suggested (unlike Center's code, which is scoped to
            // a country) — Country's code is assigned globally by the
            // source document, freely editable, just validated unique.
            'code' => ['required', 'digits:2', Rule::unique('countries', 'code')],
            'name' => ['required', 'array'],
            'name.fr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
