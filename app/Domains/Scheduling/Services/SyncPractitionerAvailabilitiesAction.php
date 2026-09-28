<?php

namespace App\Domains\Scheduling\Services;

use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a practitioner's weekly schedule wholesale (delete then
 * recreate) instead of diffing rows. Safe because nothing references
 * practitioner_availabilities.id by foreign key — AvailableSlotsResolver
 * and appointments only query by practitioner_id + day_of_week.
 */
class SyncPractitionerAvailabilitiesAction
{
    /**
     * @param  array<int, array{day_of_week: int, start_time: string, end_time: string}>  $slots
     */
    public function handle(Practitioner $practitioner, array $slots): void
    {
        // Transaction so a concurrent reader never sees the emptied schedule.
        DB::transaction(function () use ($practitioner, $slots) {
            $practitioner->availabilities()->delete();

            foreach ($slots as $slot) {
                $practitioner->availabilities()->create([
                    'day_of_week' => $slot['day_of_week'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ]);
            }
        });
    }
}
