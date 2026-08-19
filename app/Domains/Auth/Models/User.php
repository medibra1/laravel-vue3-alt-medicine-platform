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

    protected $fillable = ['name', 'email', 'password', 'locale'];

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
}
