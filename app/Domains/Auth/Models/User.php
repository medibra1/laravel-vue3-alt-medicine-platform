<?php

namespace App\Domains\Auth\Models;

use Database\Factories\Domains\Auth\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'locale', 'is_active', 'email_verified_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * super_admin is assigned as a spatie "global" role (team_id null on
     * the role itself) under a sentinel team pivot — see
     * RolesAndPermissionsSeeder. Checked here via a raw, team-unscoped
     * query rather than hasRole(), which is always filtered by whatever
     * team is currently active.
     */
    public function isSuperAdmin(): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', static::class)
            ->where('roles.name', 'super_admin')
            ->exists();
    }

    /**
     * 'admin' follows the exact same global-role sentinel pattern as
     * super_admin — see RolesAndPermissionsSeeder and isSuperAdmin().
     * Distinct from super_admin at the policy level (an admin can't
     * target another admin/super_admin), never here.
     */
    public function isAdmin(): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', static::class)
            ->where('roles.name', 'admin')
            ->exists();
    }

    /**
     * Whether this user has the 'manager' role, regardless of which
     * center (team_id) it was assigned under — a raw, team-unscoped
     * check like isSuperAdmin()/isAdmin(). Needed because hasRole()
     * filters by the *currently active* permissions team (the acting
     * user's own team), not the target model's — evaluating
     * $target->hasRole('manager') from an admin/super_admin request
     * (whose active team is null) would always resolve false otherwise.
     */
    public function isManager(): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', static::class)
            ->where('roles.name', 'manager')
            ->exists();
    }

    /**
     * Whether this user has a 'practitioner' role assignment on at
     * least one center — same raw, team-unscoped check family as
     * isManager(). Doesn't say *which* center(s); see
     * accessibleCenterIds() for that.
     */
    public function isPractitioner(): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', static::class)
            ->where('roles.name', 'practitioner')
            ->exists();
    }

    /**
     * The first (lowest id) center this user manages, if any — a
     * manager can now manage several centers (see managedCenterIds()),
     * accumulated the same way a practitioner accumulates centers.
     * Kept as a single value for the few call sites that genuinely need
     * just one — the Practitioner record auto-created for a manager
     * (its own center_id column is not itself multi-center, see
     * UserController::createPractitionerRecord()) and UserResource's
     * `center`/`center_id` fields, kept for backward compatibility with
     * anything still reading the singular shape. Never used to resolve
     * the request's *active* center any more — see EnsureCenterAccess,
     * which now treats manager exactly like practitioner
     * (accessibleCenterIds() + session('active_center_id')).
     */
    public function managedCenterId(): ?int
    {
        return $this->managedCenterIds()[0] ?? null;
    }

    /**
     * Every center this user manages — team_id of each 'manager' role
     * assignment. Extended 2026-08-26 from a single center to several,
     * the same accumulate-don't-replace shape practitioner already had
     * (see UserController::assignRoleToCenters()).
     *
     * @return array<int, int>
     */
    public function managedCenterIds(): array
    {
        return $this->centerIdsForRole('manager');
    }

    /**
     * Every center this user has a 'practitioner' role assignment on —
     * unlike manager used to be, a practitioner can be granted access to
     * several centers (one row per center in model_has_roles, never
     * replaced). Same raw, team-unscoped query family as isSuperAdmin()/
     * managedCenterId().
     *
     * @return array<int, int>
     */
    public function practitionerCenterIds(): array
    {
        return $this->centerIdsForRole('practitioner');
    }

    /**
     * Every center this user can act on for center-scoped work — the
     * union of centers managed and centers granted practitioner access
     * to (a manager is very often also a practitioner on their own
     * center(s), see RolePermissions::manager()'s docblock). Used by
     * EnsureCenterAccess to resolve/validate the request's active
     * center, and by AppCenterSwitcher/HandleInertiaRequests to decide
     * whether a switcher is shown at all.
     *
     * @return array<int, int>
     */
    public function accessibleCenterIds(): array
    {
        return collect($this->managedCenterIds())
            ->merge($this->practitionerCenterIds())
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function centerIdsForRole(string $role): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', static::class)
            ->where('roles.name', $role)
            ->orderBy('model_has_roles.team_id')
            ->pluck('model_has_roles.team_id')
            ->map(fn ($teamId) => (int) $teamId)
            ->unique()
            ->values()
            ->all();
    }
}
