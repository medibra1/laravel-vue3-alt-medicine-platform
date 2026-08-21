<?php

namespace App\Domains\Practitioners\Http\Requests;

use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePractitionerRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Practitioner $practitioner */
        $practitioner = $this->route('practitioner');

        return $this->user()->can('update', $practitioner);
    }

    /**
     * Transferring a practitioner between centers isn't a feature yet —
     * center_id is intentionally not editable here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Practitioner $practitioner */
        $practitioner = $this->route('practitioner');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'matricule' => [
                'required',
                'digits:3',
                Rule::unique('practitioners')
                    ->where('center_id', $practitioner->center_id)
                    ->ignore($practitioner),
            ],
            'level' => ['nullable', 'integer', 'min:0'],
            'hired_at' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
