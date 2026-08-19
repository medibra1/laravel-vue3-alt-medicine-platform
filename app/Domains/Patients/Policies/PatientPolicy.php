<?php

namespace App\Domains\Patients\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\Patient;

class PatientPolicy
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
        return $user->can('patients.viewAny');
    }

    public function view(User $user, Patient $patient): bool
    {
        return $user->can('patients.view') && $this->managesCenter($patient->intake_center_id);
    }

    /**
     * Center scoping for create() is enforced by StorePatientDraftRequest
     * (it forces intake_center_id to the manager's own team) rather than
     * here — there's no target Patient instance yet to check against.
     */
    public function create(User $user): bool
    {
        return $user->can('patients.create');
    }

    public function update(User $user, Patient $patient): bool
    {
        return $user->can('patients.update') && $this->managesCenter($patient->intake_center_id);
    }

    public function confirm(User $user, Patient $patient): bool
    {
        return $user->can('patients.update') && $this->managesCenter($patient->intake_center_id);
    }

    public function delete(User $user, Patient $patient): bool
    {
        return $user->can('patients.delete') && $this->managesCenter($patient->intake_center_id);
    }

    /**
     * A manager only acts on patients of the center that
     * EnsureCenterAccess resolved as the request's active team.
     */
    protected function managesCenter(int $centerId): bool
    {
        return getPermissionsTeamId() === $centerId;
    }
}
