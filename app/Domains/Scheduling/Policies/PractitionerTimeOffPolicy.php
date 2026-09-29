<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Scheduling\Models\PractitionerTimeOff;

/**
 * Same gates as PractitionerAvailabilityPolicy — time offs are managed
 * by whoever manages the practitioner's schedule.
 */
class PractitionerTimeOffPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('appointments.viewAny');
    }

    /**
     * Center scoping is enforced by StorePractitionerTimeOffRequest, same
     * split as PractitionerAvailabilityPolicy::create().
     */
    public function create(User $user): bool
    {
        return $user->can('appointments.update');
    }

    public function update(User $user, PractitionerTimeOff $timeOff): bool
    {
        return $user->can('appointments.update') && $this->managesCenter($timeOff->practitioner->center_id);
    }

    public function delete(User $user, PractitionerTimeOff $timeOff): bool
    {
        return $user->can('appointments.update') && $this->managesCenter($timeOff->practitioner->center_id);
    }

    protected function managesCenter(int $centerId): bool
    {
        return getPermissionsTeamId() === $centerId;
    }
}
