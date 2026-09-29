<?php

namespace App\Domains\Scheduling\Services;

use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use App\Domains\Scheduling\Models\PractitionerTimeOff;
use Carbon\CarbonImmutable;

/**
 * Slices a practitioner's recurring weekly availability for one day into
 * fixed-length slots, dropping any that overlap an already-booked
 * appointment. No day found in practitioner_availabilities for that
 * weekday simply means the practitioner doesn't work that day — an
 * empty result, not an error. A day covered by a PractitionerTimeOff
 * returns no slots either, whatever the weekly availability says.
 */
class AvailableSlotsResolver
{
    /**
     * @return array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function resolve(int $practitionerId, CarbonImmutable $date, int $durationMinutes): array
    {
        if (PractitionerTimeOff::query()->covering($practitionerId, $date)->exists()) {
            return [];
        }

        $dayAvailabilities = PractitionerAvailability::query()
            ->where('practitioner_id', $practitionerId)
            ->where('day_of_week', $date->dayOfWeek)
            ->get();

        if ($dayAvailabilities->isEmpty()) {
            return [];
        }

        $bookedAppointments = Appointment::query()
            ->where('practitioner_id', $practitionerId)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->whereBetween('starts_at', [$date->startOfDay(), $date->endOfDay()])
            ->get(['starts_at', 'duration_minutes']);

        $slots = [];

        foreach ($dayAvailabilities as $availability) {
            $rangeStart = $date->setTimeFrom($availability->start_time);
            $rangeEnd = $date->setTimeFrom($availability->end_time);

            $slotStart = $rangeStart;
            $slotEnd = $slotStart->addMinutes($durationMinutes);

            while ($slotEnd->lte($rangeEnd)) {
                $overlapsBooked = $bookedAppointments->contains(
                    fn (Appointment $appointment) => AppointmentConflictChecker::overlaps($slotStart, $slotEnd, $appointment->starts_at, $appointment->ends_at),
                );

                if (! $overlapsBooked) {
                    $slots[] = ['starts_at' => $slotStart, 'ends_at' => $slotEnd];
                }

                $slotStart = $slotEnd;
                $slotEnd = $slotStart->addMinutes($durationMinutes);
            }
        }

        usort($slots, fn ($a, $b) => $a['starts_at']->timestamp <=> $b['starts_at']->timestamp);

        return $slots;
    }
}
