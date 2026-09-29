<?php

namespace App\Domains\Scheduling\Rules;

use App\Domains\Scheduling\Models\PractitionerTimeOff;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejects an appointment whose day falls inside one of the
 * practitioner's time offs. Reads practitioner_id/starts_at off the
 * owning FormRequest, same pattern as NoAppointmentConflict.
 */
class NoTimeOffConflict implements ValidationRule
{
    public function __construct(private readonly FormRequest $request) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $practitionerId = $this->request->integer('practitioner_id');
        $startsAt = $this->request->input('starts_at');

        if (! $practitionerId || ! $startsAt) {
            return;
        }

        if (PractitionerTimeOff::query()->covering($practitionerId, CarbonImmutable::parse($startsAt))->exists()) {
            $fail('Ce praticien est en congé à cette date.');
        }
    }
}
