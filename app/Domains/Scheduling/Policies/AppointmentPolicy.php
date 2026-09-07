<?php

namespace App\Domains\Scheduling\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Scheduling\Models\Appointment;

class AppointmentPolicy
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

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.view') && $this->managesCenter($appointment->center_id);
    }

    /**
     * Center scoping for create() is enforced by StoreAppointmentRequest
     * (it forces center_id to the manager's own team) rather than here —
     * there's no target Appointment instance yet to check against, same
     * split already used by PatientPolicy/TreatmentPolicy.
     */
    public function create(User $user): bool
    {
        return $user->can('appointments.create');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.update') && $this->managesCenter($appointment->center_id);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.cancel') && $this->managesCenter($appointment->center_id);
    }

    /**
     * A manager only acts on appointments of the center that
     * EnsureCenterAccess resolved as the request's active team.
     */
    protected function managesCenter(int $centerId): bool
    {
        return getPermissionsTeamId() === $centerId;
    }
}
