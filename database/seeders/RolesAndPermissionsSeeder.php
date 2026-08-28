<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permission definitions are global (the permissions table isn't
 * team-scoped). Role *definitions* can be global too (team_id null on
 * the roles row), but role *assignments* (model_has_roles) always need
 * a concrete team_id in teams mode — super_admin is assigned under a
 * sentinel pivot (0, never a real center id) and checked without team
 * filtering via User::isSuperAdmin(), since spatie/laravel-permission
 * has no true cross-team role lookup. The per-center 'manager' role is
 * created lazily once a center actually has a manager to assign.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        collect([
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
            'centers.viewAny',
            'centers.view',
            'centers.create',
            'centers.update',
            'centers.delete',
            'users.viewAny',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ])->each(fn (string $name) => Permission::findOrCreate($name, 'web'));

        // Role::create()/findOrCreate() auto-stamp team_id from the
        // currently active team when teams mode is on — bypass that via
        // the query builder directly so this role's team_id stays null.
        $superAdmin = Role::query()->firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web', 'team_id' => null],
        );
        $superAdmin->syncPermissions(Permission::all());

        // 'admin' is a second global (team_id null) role, same sentinel
        // pattern as super_admin — see User::isAdmin(). Gets every
        // permission too; the distinction from super_admin is enforced
        // at the policy level (UserPolicy/CenterPolicy), not here: an
        // admin can manage centers/users but never target another
        // admin/super_admin.
        $admin = Role::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web', 'team_id' => null],
        );
        $admin->syncPermissions(Permission::all());
    }
}
