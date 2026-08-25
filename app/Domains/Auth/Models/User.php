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
     * The single center this user manages, if any — the team_id of
     * their 'manager' role assignment. A manager is scoped to one
     * center only (see CLAUDE.md); used by EnsureCenterAccess to set
     * the active permissions team for the request.
     */
    public function managedCenterId(): ?int
    {
        $teamId = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', static::class)
            ->where('roles.name', 'manager')
            ->value('model_has_roles.team_id');

        return $teamId !== null ? (int) $teamId : null;
    }

    /**
     * Every center this user has a 'practitioner' role assignment on —
     * unlike manager, a practitioner can be granted access to several
     * centers (one row per center in model_has_roles, never replaced).
     * Same raw, team-unscoped query family as isSuperAdmin()/
     * managedCenterId(); used by EnsureCenterAccess to resolve/validate
     * the request's active center for a practitioner account.
     *
     * @return array<int, int>
     */
    public function accessibleCenterIds(): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->getKey())
            ->where('model_has_roles.model_type', static::class)
            ->where('roles.name', 'practitioner')
            ->orderBy('model_has_roles.team_id')
            ->pluck('model_has_roles.team_id')
            ->map(fn ($teamId) => (int) $teamId)
            ->unique()
            ->values()
            ->all();
    }
}
