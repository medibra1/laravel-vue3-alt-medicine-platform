<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\Center;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a center's weekly opening hours wholesale (delete then
 * recreate), same approach as SyncPractitionerAvailabilitiesAction. Safe
 * because nothing references center_operating_hours.id by foreign key.
 */
class SyncCenterOperatingHoursAction
{
    /**
     * @param  array<int, array{day_of_week: int, start_time: string, end_time: string}>  $slots
     */
    public function handle(Center $center, array $slots): void
    {
        // Transaction so the agenda never reads a momentarily emptied week.
        DB::transaction(function () use ($center, $slots) {
            $center->operatingHours()->delete();

            foreach ($slots as $slot) {
                $center->operatingHours()->create([
                    'day_of_week' => $slot['day_of_week'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ]);
            }
        });
    }
}
