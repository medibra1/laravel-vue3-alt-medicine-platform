<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Http\Requests\Concerns\ValidatesWeeklySlots;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Replaces a practitioner's whole weekly schedule in one go. Center
 * scoping lives in PractitionerAvailabilityPolicy::sync() since the
 * target practitioner is already route-bound here.
 */
class SyncPractitionerAvailabilitiesRequest extends FormRequest
{
    use ValidatesWeeklySlots;

    public function authorize(): bool
    {
        /** @var Practitioner $practitioner */
        $practitioner = $this->route('practitioner');

        return $this->user()->can('sync', [PractitionerAvailability::class, $practitioner]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->slotRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateNoOverlappingSlots($validator));
    }
}
