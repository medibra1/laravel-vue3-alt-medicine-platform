<?php

namespace App\Domains\Scheduling\Services;

use App\Domains\Scheduling\Models\Appointment;
use Carbon\CarbonInterface;

/**
 * Single source of truth for "does this practitioner already have
 * something on this time window" — used both by request validation
 * (NoAppointmentConflict) and by AvailableSlotsResolver (which reuses
 * the same overlap window when carving up a day into free slots), so
 * the two can never disagree on what counts as a conflict.
 */
class AppointmentConflictChecker
{
    public function hasConflict(int $practitionerId, CarbonInterface $startsAt, int $durationMinutes, ?int $excludingAppointmentId = null): bool
    {
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);

        // "starts_at < end_b" narrows the SQL candidates to anything that
        // *could* overlap; the other half of the overlap test
        // (existing.ends_at > startsAt) needs duration_minutes added to
        // starts_at, which a raw DATE_ADD() expression could do but only
        // on MySQL — this project runs sqlite in dev/tests (see
        // .env/phpunit.xml) — so the narrowed candidates are checked in
        // PHP via overlaps() instead of pushing the whole comparison into
        // SQL.
        return Appointment::query()
            ->where('practitioner_id', $practitionerId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->when($excludingAppointmentId, fn ($query) => $query->whereKeyNot($excludingAppointmentId))
            ->where('starts_at', '<', $endsAt)
            ->get(['starts_at', 'duration_minutes'])
            ->contains(fn (Appointment $appointment) => self::overlaps($startsAt, $endsAt, $appointment->starts_at, $appointment->ends_at));
    }

    /**
     * Classic interval overlap (start_a < end_b AND end_a > start_b) —
     * shared with AvailableSlotsResolver so a slot is never offered as
     * free by one service while the other would flag it as conflicting.
     */
    public static function overlaps(CarbonInterface $startA, CarbonInterface $endA, CarbonInterface $startB, CarbonInterface $endB): bool
    {
        return $startA->lt($endB) && $endA->gt($startB);
    }
}
