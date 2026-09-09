<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Scheduling\Models\PractitionerAvailability;

class PractitionerAvailabilityPolicy
{
    /**
     * super_admin bypasses every ability below — see User::isSuperAdmin().
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('appointments.viewAny');
    }

    public function view(User $user, PractitionerAvailability $availability): bool
    {
        return $user->can('appointments.view') && $this->managesCenter($availability->practitioner->center_id);
    }

    /**
     * Center scoping for create() is enforced by
     * StorePractitionerAvailabilityRequest (it checks the target
     * practitioner's center against the manager's own team) rather than
     * here — same split already used for Practitioner/Patient/Treatment.
     */
    public function create(User $user): bool
    {
        return $user->can('appointments.update');
    }

    public function update(User $user, PractitionerAvailability $availability): bool
    {
        return $user->can('appointments.update') && $this->managesCenter($availability->practitioner->center_id);
    }

    public function delete(User $user, PractitionerAvailability $availability): bool
    {
        return $user->can('appointments.update') && $this->managesCenter($availability->practitioner->center_id);
    }

    /**
     * A manager only acts on availabilities of practitioners in the
     * center that EnsureCenterAccess resolved as the request's active
     * team.
     */
    protected function managesCenter(int $centerId): bool
    {
        return getPermissionsTeamId() === $centerId;
    }
}
