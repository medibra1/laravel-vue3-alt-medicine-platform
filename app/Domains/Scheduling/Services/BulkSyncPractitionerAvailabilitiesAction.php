<?php

namespace App\Domains\Scheduling\Services;

use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applies the same weekly schedule to several practitioners, all or
 * nothing. Each practitioner gets its own independent rows, so any of
 * them can be customised individually afterwards.
 */
class BulkSyncPractitionerAvailabilitiesAction
{
    public function __construct(private SyncPractitionerAvailabilitiesAction $syncAction) {}

    /**
     * @param  Collection<int, Practitioner>  $practitioners
     * @param  array<int, array{day_of_week: int, start_time: string, end_time: string}>  $slots
     */
    public function handle(Collection $practitioners, array $slots): void
    {
        DB::transaction(function () use ($practitioners, $slots) {
            foreach ($practitioners as $practitioner) {
                $this->syncAction->handle($practitioner, $slots);
            }
        });
    }
}
