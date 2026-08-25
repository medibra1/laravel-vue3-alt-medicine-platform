<?php

namespace App\Domains\Auth\Support;

/**
 * Permission sets for the two center-scoped roles that are created
 * lazily (not seeded up front, since they only exist once a center
 * actually has a manager/practitioner assigned — see
 * RolesAndPermissionsSeeder's docblock). Centralized here so
 * UserController::assignRole() (manager) and
 * PractitionerController::grantPractitionerAccessToCenter() (practitioner)
 * can't drift apart on what each role is actually allowed to do.
 */
class RolePermissions
{
    /** @return array<int, string> */
    public static function manager(): array
    {
        return [
            'practitioners.viewAny',
            'practitioners.view',
            'practitioners.create',
            'practitioners.update',
            'practitioners.delete',
            'patients.viewAny',
            'patients.view',
            'patients.create',
            'patients.update',
            'patients.delete',
            'treatments.viewAny',
            'treatments.view',
            'treatments.create',
            'treatments.update',
            'treatments.delete',
            'treatment_sessions.viewAny',
            'treatment_sessions.view',
            'treatment_sessions.create',
            'treatment_sessions.update',
            'treatment_sessions.delete',
        ];
    }

    /**
     * Read-only on the two clinical domains a practitioner needs to see
     * for the center they're currently active on — never create/update/
     * delete, those stay manager-only (see PatientPolicy/TreatmentPolicy).
     *
     * @return array<int, string>
     */
    public static function practitioner(): array
    {
        return [
            'patients.viewAny',
            'patients.view',
            'treatments.viewAny',
            'treatments.view',
        ];
    }
}
