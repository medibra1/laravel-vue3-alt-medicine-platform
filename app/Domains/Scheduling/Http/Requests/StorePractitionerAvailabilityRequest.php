<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePractitionerAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PractitionerAvailability::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'practitioner_id' => ['required', 'integer', 'exists:practitioners,id'],
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    /**
     * A manager may only add hours for a practitioner of the center
     * that's currently active for them — super_admin isn't scoped this
     * way (see EnsureCenterAccess).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->user()->isSuperAdmin()) {
                return;
            }

            $practitioner = Practitioner::query()->find($this->integer('practitioner_id'));

            if ($practitioner && $practitioner->center_id !== getPermissionsTeamId()) {
                $validator->errors()->add('practitioner_id', __('Ce praticien n\'appartient pas à votre centre.'));
            }
        });
    }
}
