<?php

use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\ManagerAssignedNotification;
use App\Domains\Auth\Notifications\WelcomeSetPasswordNotification;
use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

test('guests are redirected to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('manager cannot access the users list', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.users.index'));

    $response->assertForbidden();
});

test('admin can list users', function () {
    $admin = actingAsAdmin();
    User::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
});

test('super admin can create a manager directly with a password', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    Notification::fake();

    $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Nouveau Manager',
        'email' => 'manager@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'direct',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'manager@example.com')->firstOrFail();
    expect($user->is_active)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->isManager())->toBeTrue();
    expect($user->managedCenterIds())->toBe([$center->id]);

    Notification::assertSentTo($user, ManagerAssignedNotification::class);
    Notification::assertNotSentTo($user, WelcomeSetPasswordNotification::class);
});

test('creating a manager also creates a linked Practitioner record on the same center', function () {
    // A manager very often also treats patients themselves — the
    // "Praticien" select on the Treatment wizard reads from the real
    // practitioners table (treatments.practitioner_id is a genuine FK),
    // so a manager with no Practitioner row could never be picked
    // there. See UserController::ensurePractitionerAccess().
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    Notification::fake();

    $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Amina Traitante',
        'email' => 'amina-manager@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'invite',
    ]);

    $user = User::query()->where('email', 'amina-manager@example.com')->firstOrFail();
    $practitioner = Practitioner::query()->where('user_id', $user->id)->firstOrFail();

    expect($practitioner->center_id)->toBe($center->id);
    expect($practitioner->first_name)->toBe('Amina');
    expect($practitioner->last_name)->toBe('Traitante');
    expect($practitioner->email)->toBe('amina-manager@example.com');
    expect($practitioner->full_code)->not->toBeEmpty();
});

test('creating an admin does not create a Practitioner record', function () {
    // admin/super_admin are global, center-less roles — there is no
    // center to attach a Practitioner row to, and RolePermissions
    // never intended them to appear in a center-scoped "Praticien"
    // select.
    $superAdmin = actingAsSuperAdmin();

    Notification::fake();

    $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Global Admin',
        'email' => 'global-admin@example.com',
        'role' => 'admin',
        'creation_mode' => 'invite',
    ]);

    $user = User::query()->where('email', 'global-admin@example.com')->firstOrFail();
    expect(Practitioner::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('updating a manager who is already linked to a Practitioner does not create a second row', function () {
    // Reachable if this person was earlier granted practitioner access
    // on another center through the Practitioner "join an existing
    // account" flow (or was already backfilled by a previous edit) —
    // practitioners.user_id is unique at the DB level, a naive second
    // create() would throw.
    $superAdmin = actingAsSuperAdmin();
    $otherCenter = Center::factory()->create();
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);
    Practitioner::factory()->for($otherCenter)->create(['user_id' => $manager->id]);

    $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $manager), [
        'name' => $manager->name,
        'email' => $manager->email,
        'role' => 'manager',
        'center_ids' => [$center->id],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect(Practitioner::query()->where('user_id', $manager->id)->count())->toBe(1);
});

test('updating an existing manager backfills a Practitioner record if missing', function () {
    // Covers a manager created before automatic Practitioner creation
    // existed (or created directly in the DB) — the next edit through
    // the admin UI is enough to backfill it, no separate migration/
    // command needed.
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);

    $this->actingAs($superAdmin)->put(route('admin.users.update', $manager), [
        'name' => $manager->name,
        'email' => $manager->email,
        'role' => 'manager',
        'center_ids' => [$center->id],
    ]);

    expect(Practitioner::query()->where('user_id', $manager->id)->exists())->toBeTrue();
});

test('admin can create a manager via invitation, no password set yet', function () {
    $admin = actingAsAdmin();
    $center = Center::factory()->create();

    Notification::fake();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Manager Invité',
        'email' => 'invited@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'invite',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'invited@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();
    expect($user->isManager())->toBeTrue();

    Notification::assertSentTo($user, WelcomeSetPasswordNotification::class);
    Notification::assertSentTo($user, ManagerAssignedNotification::class);
});

test('invite mode with empty password fields (as the frontend actually sends them) still succeeds', function () {
    // Regression: the Vue form always submits password/password_confirmation
    // in the JSON body, even blank, when creation_mode is 'invite' — an
    // empty string surviving ConvertEmptyStringsToNull as null previously
    // still ran Password::defaults() against it (rules() stacked
    // 'prohibited' alongside Password::defaults() in the same array),
    // failing validation and silently redirecting back with errors that
    // no existing test exercised (they all omit the keys entirely).
    $admin = actingAsAdmin();
    $center = Center::factory()->create();

    Notification::fake();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Manager Invité Vide',
        'email' => 'invited-empty@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'invite',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('admin.users.index'));
    expect(User::query()->where('email', 'invited-empty@example.com')->exists())->toBeTrue();
});

test('a manager role assignment left behind by a user deleted outside destroy() does not block a new manager', function () {
    // Regression: found via real browser testing — destroy() only ever
    // deactivates a user (see its docblock), so the only sanctioned path
    // never leaves an orphaned model_has_roles row (HasRoles detaches
    // roles on Eloquent delete()). The orphan this actually reproduces
    // comes from a raw DB deletion (a direct `DB::table('users')->delete()`,
    // bypassing that trait) — exactly what happened once in this
    // project's dev DB during manual test-data cleanup. That orphaned row
    // previously still matched the "already has a manager" check (a plain
    // model_has_roles/roles join, no users join), showing a real
    // "Ce centre a déjà un manager." error to super_admin/admin for a
    // center that, from the UI's point of view, genuinely has no manager
    // anymore.
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $orphan = actingAsManagerOf($center);
    DB::table('users')->where('id', $orphan->id)->delete();

    $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Nouveau Manager',
        'email' => 'replacement-manager@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'invite',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('admin.users.index'));
    expect(User::query()->where('email', 'replacement-manager@example.com')->exists())->toBeTrue();
});

test('an admin cannot create another admin', function () {
    $admin = actingAsAdmin();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Faux Admin',
        'email' => 'fakeadmin@example.com',
        'role' => 'admin',
        'creation_mode' => 'invite',
    ]);

    $response->assertSessionHasErrors('role');
    expect(User::query()->where('email', 'fakeadmin@example.com')->exists())->toBeFalse();
});

test('super admin can create an admin', function () {
    $superAdmin = actingAsSuperAdmin();

    Notification::fake();

    $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Vrai Admin',
        'email' => 'realadmin@example.com',
        'role' => 'admin',
        'creation_mode' => 'invite',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'realadmin@example.com')->firstOrFail();
    expect($user->isAdmin())->toBeTrue();
});

test('creating a manager on a center that already has one fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    actingAsManagerOf($center);

    $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Second Manager',
        'email' => 'second@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'invite',
    ]);

    $response->assertSessionHasErrors('center_ids');
    expect(User::query()->where('email', 'second@example.com')->exists())->toBeFalse();
});

test('duplicate email is rejected', function () {
    $superAdmin = actingAsSuperAdmin();
    $existing = User::factory()->create(['email' => 'taken@example.com']);
    $center = Center::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Doublon',
        'email' => 'taken@example.com',
        'role' => 'manager',
        'center_ids' => [$center->id],
        'creation_mode' => 'invite',
    ]);

    $response->assertSessionHasErrors('email');
    expect(User::query()->where('email', 'taken@example.com')->count())->toBe(1);
});

test('admin can update a manager but not reassign to an already-managed center', function () {
    $admin = actingAsAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    $manager = actingAsManagerOf($centerA);
    actingAsManagerOf($centerB);

    $response = $this->actingAs($admin)->put(route('admin.users.update', $manager), [
        'name' => $manager->name,
        'email' => $manager->email,
        'role' => 'manager',
        'center_ids' => [$centerB->id],
    ]);

    $response->assertSessionHasErrors('center_ids');
});

test('admin cannot update another admin', function () {
    $admin = actingAsAdmin();
    $otherAdmin = actingAsAdmin();

    $response = $this->actingAs($admin)->put(route('admin.users.update', $otherAdmin), [
        'name' => 'Renamed',
        'email' => $otherAdmin->email,
        'role' => 'manager',
        'center_ids' => [Center::factory()->create()->id],
    ]);

    $response->assertForbidden();
});

test('admin can deactivate a manager, never deletes the row', function () {
    $admin = actingAsAdmin();
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $manager));

    $response->assertRedirect(route('admin.users.index'));
    expect($manager->fresh()->is_active)->toBeFalse();
    expect(User::query()->whereKey($manager->id)->exists())->toBeTrue();
});

test('admin cannot deactivate another admin', function () {
    $admin = actingAsAdmin();
    $otherAdmin = actingAsAdmin();

    $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $otherAdmin));

    $response->assertForbidden();
    expect($otherAdmin->fresh()->is_active)->toBeTrue();
});

test('a deactivated user cannot log in', function () {
    $user = User::factory()->create(['is_active' => false, 'password' => bcrypt('password')]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('an active user can log in normally', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('a manager can be assigned to several centers at once', function () {
    // Extended 2026-08-26 from the original single-center design — a
    // manager very often also manages more than one center, the same
    // accumulate-across-centers shape practitioner already had.
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();

    Notification::fake();

    $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Multi Center Manager',
        'email' => 'multi-center@example.com',
        'role' => 'manager',
        'center_ids' => [$centerA->id, $centerB->id],
        'creation_mode' => 'invite',
    ]);

    $manager = User::query()->where('email', 'multi-center@example.com')->firstOrFail();
    expect($manager->managedCenterIds())->toEqual(collect([$centerA->id, $centerB->id])->sort()->values()->all());

    // One notification per newly assigned center.
    Notification::assertSentToTimes($manager, ManagerAssignedNotification::class, 2);
});

test('editing a manager can add a second center without losing the first', function () {
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    $manager = actingAsManagerOf($centerA);

    Notification::fake();

    $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $manager), [
        'name' => $manager->name,
        'email' => $manager->email,
        'role' => 'manager',
        'center_ids' => [$centerA->id, $centerB->id],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($manager->managedCenterIds())->toEqual(collect([$centerA->id, $centerB->id])->sort()->values()->all());

    // Only the newly added center triggers a notification — the
    // manager was already managing centerA before this edit.
    Notification::assertSentToTimes($manager, ManagerAssignedNotification::class, 1);
});

test('editing a manager to drop a center actually revokes it', function () {
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    $manager = actingAsManagerOf($centerA);
    setPermissionsTeamId($centerB->id);
    $role = Role::findOrCreate('manager', 'web');
    $manager->assignRole($role);
    setPermissionsTeamId(null);

    expect($manager->managedCenterIds())->toEqual(collect([$centerA->id, $centerB->id])->sort()->values()->all());

    $this->actingAs($superAdmin)->put(route('admin.users.update', $manager), [
        'name' => $manager->name,
        'email' => $manager->email,
        'role' => 'manager',
        'center_ids' => [$centerA->id],
    ]);

    expect($manager->managedCenterIds())->toBe([$centerA->id]);
});

test('a manager managing several centers gets practitioner access on every one of them', function () {
    // Regression: RolePermissions::manager()'s docblock — a manager
    // very often also treats patients themselves. The "Praticien"
    // select on the Treatment wizard now checks a 'practitioner' role
    // grant per center (see ResolvesPractitionerOptions), not just
    // Practitioner.center_id — this manager should be pickable on both
    // centers they manage, not only the one their Practitioner row
    // happens to point to.
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();

    Notification::fake();

    $this->actingAs($superAdmin)->post(route('admin.users.store'), [
        'name' => 'Dual Center Manager',
        'email' => 'dual-center@example.com',
        'role' => 'manager',
        'center_ids' => [$centerA->id, $centerB->id],
        'creation_mode' => 'invite',
    ]);

    $manager = User::query()->where('email', 'dual-center@example.com')->firstOrFail();
    expect($manager->practitionerCenterIds())->toEqual(collect([$centerA->id, $centerB->id])->sort()->values()->all());

    // Still only one Practitioner row — its center_id is not itself
    // multi-center, only the role grant is.
    expect(Practitioner::query()->where('user_id', $manager->id)->count())->toBe(1);
});
