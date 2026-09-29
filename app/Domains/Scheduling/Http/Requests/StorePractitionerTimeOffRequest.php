<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\PractitionerTimeOff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePractitionerTimeOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PractitionerTimeOff::class);
    }

    /**
     * The practitioner comes from the URL — never trust a body value that
     * could point elsewhere.
     */
    protected function prepareForValidation(): void
    {
        $practitioner = $this->route('practitioner');

        if ($practitioner instanceof Practitioner) {
            $this->merge(['practitioner_id' => $practitioner->id]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'practitioner_id' => ['required', 'integer', 'exists:practitioners,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', Rule::in(['vacation', 'sick_leave', 'training', 'other'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * A manager may only add a time off for a practitioner of their
     * active center — same check as StorePractitionerAvailabilityRequest.
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
