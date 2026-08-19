<?php

namespace App\Domains\Practitioners\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Practitioners\Models\Practitioner;

class PractitionerPolicy
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
        return $user->can('practitioners.viewAny');
    }

    public function view(User $user, Practitioner $practitioner): bool
    {
        return $user->can('practitioners.view') && $this->managesCenter($practitioner->center_id);
    }

    /**
     * Center scoping for create() is enforced by StorePractitionerRequest
     * (it forces center_id to the manager's own team) rather than here —
     * there's no target Practitioner instance yet to check against.
     */
    public function create(User $user): bool
    {
        return $user->can('practitioners.create');
    }

    public function update(User $user, Practitioner $practitioner): bool
    {
        return $user->can('practitioners.update') && $this->managesCenter($practitioner->center_id);
    }

    public function delete(User $user, Practitioner $practitioner): bool
    {
        return $user->can('practitioners.delete') && $this->managesCenter($practitioner->center_id);
    }

    /**
     * A manager only acts on practitioners of the center that
     * EnsureCenterAccess resolved as the request's active team.
     */
    protected function managesCenter(int $centerId): bool
    {
        return getPermissionsTeamId() === $centerId;
    }
}
