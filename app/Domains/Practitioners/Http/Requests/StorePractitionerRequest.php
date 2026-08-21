<?php

namespace App\Domains\Practitioners\Http\Requests;

use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePractitionerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Practitioner::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            // A manager may not choose the center at all — it's forced to
            // the one EnsureCenterAccess resolved for them, see centerId().
            'center_id' => [$this->user()->isSuperAdmin() ? 'required' : 'prohibited', 'integer', 'exists:centers,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            // full_code = country + center + matricule, so within one
            // center the matricule is what actually drives uniqueness.
            // Auto-suggested by PractitionerCodeGenerator::suggestNextMatricule()
            // on the frontend, but this field stays editable/overridable.
            'matricule' => [
                'required',
                'digits:3',
                Rule::unique('practitioners')->where('center_id', $this->centerId()),
            ],
            'level' => ['nullable', 'integer', 'min:0'],
            'hired_at' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->user()->isSuperAdmin() && $this->centerId() === null) {
                $validator->errors()->add('center_id', __('Vous ne gérez aucun centre.'));
            }
        });
    }

    public function centerId(): ?int
    {
        return $this->user()->isSuperAdmin()
            ? $this->integer('center_id')
            : $this->user()->managedCenterId();
    }
}
