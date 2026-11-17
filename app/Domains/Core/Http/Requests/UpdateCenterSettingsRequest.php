<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Core\Support\Modules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCenterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSettings', $this->route('center'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'timezone' => ['required', 'timezone:all'],
            'modules' => ['sometimes', 'array'],
            'modules.*' => [Rule::in(Modules::all())],
            'settings' => ['required', 'array'],
            'settings.session_price_in_person' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'settings.session_price_remote' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'settings.default_session_duration' => ['required', 'integer', 'min:5', 'max:600'],
            'settings.default_modality' => ['required', Rule::in(['in_person', 'remote'])],
            'care_prices' => ['sometimes', 'array'],
            'care_prices.*.care_item_id' => ['required', 'integer', 'exists:care_items,id'],
            'care_prices.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}
