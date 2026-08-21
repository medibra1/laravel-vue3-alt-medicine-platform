<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Core\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Zone::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('zones', 'code')],
            'name' => ['required', 'array'],
            'name.fr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
