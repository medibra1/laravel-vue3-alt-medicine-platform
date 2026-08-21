<?php

namespace App\Domains\Patients\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\TreatmentSession;

class TreatmentSessionPolicy
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
        return $user->can('treatment_sessions.viewAny');
    }

    public function view(User $user, TreatmentSession $session): bool
    {
        return $user->can('treatment_sessions.view') && $this->managesCenter($session);
    }

    public function create(User $user): bool
    {
        return $user->can('treatment_sessions.create');
    }

    public function update(User $user, TreatmentSession $session): bool
    {
        return $user->can('treatment_sessions.update') && $this->managesCenter($session);
    }

    public function delete(User $user, TreatmentSession $session): bool
    {
        return $user->can('treatment_sessions.delete') && $this->managesCenter($session);
    }

    /**
     * A session has no center_id of its own — it's scoped via its parent
     * Treatment's center, same reasoning as TreatmentPolicy::managesCenter().
     */
    protected function managesCenter(TreatmentSession $session): bool
    {
        $centerId = $session->treatment->center_id;

        return $centerId !== null && getPermissionsTeamId() === $centerId;
    }
}
