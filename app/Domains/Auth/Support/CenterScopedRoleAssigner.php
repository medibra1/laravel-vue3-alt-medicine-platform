<?php

namespace App\Domains\Auth\Support;

use App\Domains\Auth\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Grants/revokes a center-scoped role ('manager' or 'practitioner')
 * assignment for one user on one center — the single place both roles'
 * "accumulate across centers, don't replace" behavior lives, since it
 * used to be duplicated near-identically between
 * PractitionerController::grantPractitionerAccessToCenter() and (now
 * removed) UserController::assignRole()'s manager branch. A manager
 * gaining the ability to manage several centers (2026-08-26) made that
 * duplication real rather than coincidental — both roles now behave
 * the same way, so one class owns the mechanics for both.
 *
 * admin/super_admin stay out of here — they're global (team_id null
 * under a sentinel pivot), a fundamentally different shape handled
 * directly in UserController::assignGlobalRole().
 */
class CenterScopedRoleAssigner
{
    /**
     * Grants $role to $user on $centerId if not already held there —
     * additive, never touches the user's assignments on any other
     * center. Cheap no-op (still re-syncs permissions) if already
     * granted.
     */
    public function grant(User $user, string $role, int $centerId): void
    {
        $requestTeamId = getPermissionsTeamId();

        $alreadyGranted = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->getKey())
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', $role)
            ->where('model_has_roles.team_id', $centerId)
            ->exists();

        if ($alreadyGranted) {
            setPermissionsTeamId($requestTeamId);

            return;
        }

        setPermissionsTeamId($centerId);

        $roleModel = Role::query()
            ->where('name', $role)
            ->where('guard_name', 'web')
            ->where('team_id', $centerId)
            ->first();

        if (! $roleModel) {
            $roleModel = Role::query()->create([
                'name' => $role,
                'guard_name' => 'web',
                'team_id' => $centerId,
            ]);
        }

        // Synced on every grant, not just on first creation — a role
        // created before RolePermissions::manager()/practitioner()
        // gained a new permission would otherwise stay stuck on its
        // creation-time set forever (found for practitioner on
        // 2026-08-25). Cheap no-op when the permission set hasn't
        // actually changed.
        $roleModel->syncPermissions(RolePermissions::forRole($role));

        $user->assignRole($roleModel);

        setPermissionsTeamId($requestTeamId);
    }

    /**
     * Revokes $role from $user on $centerId only — the other centers'
     * assignments (for this role or any other) are untouched.
     */
    public function revoke(User $user, string $role, int $centerId): void
    {
        DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->getKey())
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', $role)
            ->where('model_has_roles.team_id', $centerId)
            ->delete();
    }

    /**
     * Grants/revokes $role so the user ends up assigned on exactly
     * $centerIds — additions and removals in one call, driven by a
     * diff against their current assignments for this role.
     *
     * @param  array<int, int>  $centerIds
     */
    public function syncCenters(User $user, string $role, array $centerIds): void
    {
        $current = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->getKey())
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', $role)
            ->pluck('model_has_roles.team_id')
            ->map(fn ($teamId) => (int) $teamId)
            ->all();

        foreach (array_diff($centerIds, $current) as $centerId) {
            $this->grant($user, $role, $centerId);
        }

        foreach (array_diff($current, $centerIds) as $centerId) {
            $this->revoke($user, $role, $centerId);
        }
    }
}
