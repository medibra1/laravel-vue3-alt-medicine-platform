<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Core\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Zone $zone */
        $zone = $this->route('zone');

        return $this->user()->can('update', $zone);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Zone $zone */
        $zone = $this->route('zone');

        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('zones', 'code')->ignore($zone)],
            'name' => ['required', 'array'],
            'name.fr' => ['required', 'string', 'max:255'],
            'name.en' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
