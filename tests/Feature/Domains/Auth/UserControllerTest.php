<?php

use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\ManagerAssignedNotification;
use App\Domains\Auth\Notifications\WelcomeSetPasswordNotification;
use App\Domains\Core\Models\Center;
use Illuminate\Support\Facades\Notification;

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
        'center_id' => $center->id,
        'creation_mode' => 'direct',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'manager@example.com')->firstOrFail();
    expect($user->is_active)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->isManager())->toBeTrue();
    expect($user->managedCenterId())->toBe($center->id);

    Notification::assertSentTo($user, ManagerAssignedNotification::class);
    Notification::assertNotSentTo($user, WelcomeSetPasswordNotification::class);
});

test('admin can create a manager via invitation, no password set yet', function () {
    $admin = actingAsAdmin();
    $center = Center::factory()->create();

    Notification::fake();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Manager Invité',
        'email' => 'invited@example.com',
        'role' => 'manager',
        'center_id' => $center->id,
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
        'center_id' => $center->id,
        'creation_mode' => 'invite',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('admin.users.index'));
    expect(User::query()->where('email', 'invited-empty@example.com')->exists())->toBeTrue();
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
        'center_id' => $center->id,
        'creation_mode' => 'invite',
    ]);

    $response->assertSessionHasErrors('center_id');
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
        'center_id' => $center->id,
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
        'center_id' => $centerB->id,
    ]);

    $response->assertSessionHasErrors('center_id');
});

test('admin cannot update another admin', function () {
    $admin = actingAsAdmin();
    $otherAdmin = actingAsAdmin();

    $response = $this->actingAs($admin)->put(route('admin.users.update', $otherAdmin), [
        'name' => 'Renamed',
        'email' => $otherAdmin->email,
        'role' => 'manager',
        'center_id' => Center::factory()->create()->id,
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
