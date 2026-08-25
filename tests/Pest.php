<?php

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The permission set used across the Practitioners domain — defined
 * here rather than relying on RolesAndPermissionsSeeder so tests stay
 * fast (RefreshDatabase, no full seed) and explicit about what they need.
 */
function grantPractitionerPermissions(): void
{
    collect([
        'practitioners.viewAny',
        'practitioners.view',
        'practitioners.create',
        'practitioners.update',
        'practitioners.delete',
    ])->each(fn (string $name) => Permission::findOrCreate($name, 'web'));
}

/**
 * Same idea as grantPractitionerPermissions(), for the Patients domain.
 */
function grantPatientPermissions(): void
{
    collect([
        'patients.viewAny',
        'patients.view',
        'patients.create',
        'patients.update',
        'patients.delete',
    ])->each(fn (string $name) => Permission::findOrCreate($name, 'web'));
}

/**
 * Same idea as grantPractitionerPermissions(), for the Treatments domain.
 */
function grantTreatmentPermissions(): void
{
    collect([
        'treatments.viewAny',
        'treatments.view',
        'treatments.create',
        'treatments.update',
        'treatments.delete',
    ])->each(fn (string $name) => Permission::findOrCreate($name, 'web'));
}

/**
 * Same idea as grantPractitionerPermissions(), for TreatmentSession.
 */
function grantTreatmentSessionPermissions(): void
{
    collect([
        'treatment_sessions.viewAny',
        'treatment_sessions.view',
        'treatment_sessions.create',
        'treatment_sessions.update',
        'treatment_sessions.delete',
    ])->each(fn (string $name) => Permission::findOrCreate($name, 'web'));
}

/**
 * Same idea as grantPractitionerPermissions(), for Center.
 */
function grantCenterPermissions(): void
{
    collect([
        'centers.viewAny',
        'centers.view',
        'centers.create',
        'centers.update',
        'centers.delete',
    ])->each(fn (string $name) => Permission::findOrCreate($name, 'web'));
}

/**
 * Same idea as grantPractitionerPermissions(), for User management.
 */
function grantUserManagementPermissions(): void
{
    collect([
        'users.viewAny',
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
    ])->each(fn (string $name) => Permission::findOrCreate($name, 'web'));
}

/**
 * A global super_admin, mirroring RolesAndPermissionsSeeder's sentinel
 * team-pivot pattern (see User::isSuperAdmin()).
 */
function actingAsSuperAdmin(): User
{
    grantPractitionerPermissions();
    grantPatientPermissions();
    grantTreatmentPermissions();
    grantTreatmentSessionPermissions();
    grantCenterPermissions();
    grantUserManagementPermissions();

    $user = User::factory()->create();

    $role = Role::query()->firstOrCreate(
        ['name' => 'super_admin', 'guard_name' => 'web', 'team_id' => null],
    );
    $role->syncPermissions(Permission::all());

    setPermissionsTeamId(0);
    $user->assignRole('super_admin');
    setPermissionsTeamId(null);

    return $user;
}

/**
 * A global admin — same sentinel team-pivot pattern as super_admin (see
 * User::isAdmin()), but distinguished from it purely at the policy
 * level (UserPolicy/CenterPolicy), not by a smaller permission set.
 */
function actingAsAdmin(): User
{
    grantPractitionerPermissions();
    grantPatientPermissions();
    grantTreatmentPermissions();
    grantTreatmentSessionPermissions();
    grantCenterPermissions();
    grantUserManagementPermissions();

    $user = User::factory()->create();

    $role = Role::query()->firstOrCreate(
        ['name' => 'admin', 'guard_name' => 'web', 'team_id' => null],
    );
    $role->syncPermissions(Permission::all());

    setPermissionsTeamId(0);
    $user->assignRole('admin');
    setPermissionsTeamId(null);

    return $user;
}

/**
 * A manager scoped to a single center via a team-pivoted 'manager' role
 * — mirrors how EnsureCenterAccess resolves the active team at request
 * time via User::managedCenterId().
 */
function actingAsManagerOf(Center $center): User
{
    grantPractitionerPermissions();
    grantPatientPermissions();
    grantTreatmentPermissions();
    grantTreatmentSessionPermissions();

    $user = User::factory()->create();

    setPermissionsTeamId($center->id);
    $role = Role::findOrCreate('manager', 'web');
    $role->syncPermissions([
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
    ]);
    $user->assignRole('manager');
    setPermissionsTeamId(null);

    return $user;
}

/**
 * A practitioner accessible on one or more centers — unlike manager,
 * assigns a separate 'practitioner' role row per center (never
 * replacing an earlier one), mirroring
 * PractitionerController::grantPractitionerAccessToCenter().
 */
function actingAsPractitionerOf(Center ...$centers): User
{
    grantPatientPermissions();
    grantTreatmentPermissions();

    $user = User::factory()->create();

    foreach ($centers as $center) {
        setPermissionsTeamId($center->id);
        $role = Role::findOrCreate('practitioner', 'web');
        $role->syncPermissions([
            'patients.viewAny',
            'patients.view',
            'treatments.viewAny',
            'treatments.view',
        ]);
        $user->assignRole($role);
    }

    setPermissionsTeamId(null);

    return $user;
}
