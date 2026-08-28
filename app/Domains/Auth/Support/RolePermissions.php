<?php

namespace App\Domains\Auth\Support;

/**
 * Permission sets for the two center-scoped roles that are created
 * lazily (not seeded up front, since they only exist once a center
 * actually has a manager/practitioner assigned — see
 * RolesAndPermissionsSeeder's docblock). Centralized here so the two
 * CenterScopedRoleAssigner::grant() call sites (manager, practitioner)
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
     * A practitioner can register/edit patients, start/update
     * treatments, and log sessions for the center they're currently
     * active on (extended to treatments.create/update and
     * treatment_sessions.create/update 2026-08-26 — a practitioner
     * adding a patient hit a 403 trying to add that patient's
     * treatment, and would have hit the same wall adding a session
     * right after, found via real browser testing; all four were
     * read-only-only up to then, the same gap patients.create/update
     * had until 2026-08-25). Deliberately still read-only on every
     * *.delete — deleting a record outright stays manager-only (see
     * PatientPolicy/TreatmentPolicy/TreatmentSessionPolicy).
     *
     * @return array<int, string>
     */
    public static function practitioner(): array
    {
        return [
            'patients.viewAny',
            'patients.view',
            'patients.create',
            'patients.update',
            'treatments.viewAny',
            'treatments.view',
            'treatments.create',
            'treatments.update',
            'treatment_sessions.viewAny',
            'treatment_sessions.view',
            'treatment_sessions.create',
            'treatment_sessions.update',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function forRole(string $role): array
    {
        return match ($role) {
            'manager' => self::manager(),
            'practitioner' => self::practitioner(),
            default => throw new \InvalidArgumentException("No permission set defined for role '{$role}'."),
        };
    }
}
