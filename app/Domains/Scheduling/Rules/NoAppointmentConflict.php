<?php

namespace App\Domains\Scheduling\Rules;

use App\Domains\Scheduling\Services\AppointmentConflictChecker;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates that {practitioner_id, starts_at, duration_minutes} on the
 * request don't overlap another appointment already on that
 * practitioner's schedule. Reads its three inputs off the owning
 * FormRequest rather than being handed them directly, since Laravel
 * only calls a rule with the single field it's attached to.
 */
class NoAppointmentConflict implements ValidationRule
{
    public function __construct(private readonly FormRequest $request) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $practitionerId = $this->request->integer('practitioner_id');
        $durationMinutes = $this->request->integer('duration_minutes');
        $startsAt = $this->request->input('starts_at');

        if (! $practitionerId || ! $durationMinutes || ! $startsAt) {
            return;
        }

        $checker = app(AppointmentConflictChecker::class);
        $excludingAppointmentId = $this->request->route('appointment')?->id;

        if ($checker->hasConflict($practitionerId, Carbon::parse($startsAt), $durationMinutes, $excludingAppointmentId)) {
            $fail('Ce praticien a déjà un rendez-vous sur ce créneau.');
        }
    }
}
