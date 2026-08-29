<?php

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Notifications\PractitionerJoinedCenterNotification;
use Illuminate\Support\Facades\Notification;

test('grant_access false keeps the existing behavior — no user row touched', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => 'Ahmed',
        'last_name' => 'Ben Ali',
        'center_id' => $center->id,
        'matricule' => '001',
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));
    $practitioner = Practitioner::query()->firstOrFail();
    expect($practitioner->user_id)->toBeNull();
    expect(User::query()->count())->toBe(1); // just the acting super_admin
});

test('grant_access true with a new email creates a user and links it', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    Notification::fake();

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => 'Ahmed',
        'last_name' => 'Ben Ali',
        'center_id' => $center->id,
        'matricule' => '002',
        'grant_access' => true,
        'email' => 'ahmed@example.com',
        'creation_mode' => 'invite',
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));
    $practitioner = Practitioner::query()->where('matricule', '002')->firstOrFail();
    expect($practitioner->user_id)->not->toBeNull();

    $user = User::query()->findOrFail($practitioner->user_id);
    expect($user->email)->toBe('ahmed@example.com');
    expect($user->isPractitioner())->toBeTrue();
    expect($user->accessibleCenterIds())->toBe([$center->id]);
});

test('grant_access true with direct mode sets the given password and activates immediately', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => 'Ahmed',
        'last_name' => 'Ben Ali',
        'center_id' => $center->id,
        'matricule' => '003',
        'grant_access' => true,
        'email' => 'ahmed-direct@example.com',
        'creation_mode' => 'direct',
        'password' => 'SuperSecret123!',
        'password_confirmation' => 'SuperSecret123!',
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));
    $user = User::query()->where('email', 'ahmed-direct@example.com')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->is_active)->toBeTrue();
});

test('grant_access true matching an already-registered practitioner auto-joins without creating a duplicate row', function () {
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();

    Notification::fake();

    // First practitioner, created with access, on center A.
    $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => 'Ahmed',
        'last_name' => 'Ben Ali',
        'center_id' => $centerA->id,
        'matricule' => '004',
        'grant_access' => true,
        'email' => 'shared@example.com',
        'creation_mode' => 'invite',
    ])->assertRedirect(route('admin.practitioners.index'));

    expect(Practitioner::query()->count())->toBe(1);
    $originalPractitioner = Practitioner::query()->firstOrFail();
    $user = User::query()->where('email', 'shared@example.com')->firstOrFail();

    // Second submission, same email, different center — should NOT
    // create a second Practitioner row.
    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'center_id' => $centerB->id,
        'grant_access' => true,
        'email' => 'shared@example.com',
        // first_name/last_name/matricule are prohibited when joining —
        // omitted here, matching what the frontend would actually send.
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));
    expect(Practitioner::query()->count())->toBe(1);
    expect(Practitioner::query()->first()->id)->toBe($originalPractitioner->id);

    $user->refresh();
    expect($user->accessibleCenterIds())->toEqual(collect([$centerA->id, $centerB->id])->sort()->values()->all());

    Notification::assertSentTo($user, PractitionerJoinedCenterNotification::class);
});

test('auto-join succeeds even with leftover matricule/creation_mode values (as the frontend actually sends them)', function () {
    // Regression: useForm() always submits every field it knows about,
    // even ones hidden behind v-if once isJoiningExisting becomes true —
    // a leftover matricule/creation_mode from whatever was last typed
    // (or the form's own default) reached the server exactly like this,
    // and 'prohibited' on those fields silently rejected the whole
    // request with a redirect-back that looked identical to a real
    // success (302 to the same index URL). No existing test caught it
    // because they all omitted these keys outright instead of sending
    // them non-empty.
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();

    Notification::fake();

    $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => 'Ahmed',
        'last_name' => 'Ben Ali',
        'center_id' => $centerA->id,
        'matricule' => '007',
        'grant_access' => true,
        'email' => 'leftover@example.com',
        'creation_mode' => 'invite',
    ])->assertRedirect(route('admin.practitioners.index'));

    $user = User::query()->where('email', 'leftover@example.com')->firstOrFail();

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => '',
        'last_name' => '',
        'center_id' => $centerB->id,
        'matricule' => '001',
        'grade_id' => null,
        'level' => null,
        'hired_at' => null,
        'phone' => '',
        'address' => '',
        'grant_access' => true,
        'email' => 'leftover@example.com',
        'creation_mode' => 'invite',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('admin.practitioners.index'));
    expect(Practitioner::query()->count())->toBe(1);

    $user->refresh();
    expect($user->accessibleCenterIds())->toEqual(collect([$centerA->id, $centerB->id])->sort()->values()->all());
});

test('grant_access true with an email already used by a non-practitioner account is rejected', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $existingAdmin = actingAsAdmin();

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => 'Ahmed',
        'last_name' => 'Ben Ali',
        'center_id' => $center->id,
        'matricule' => '005',
        'grant_access' => true,
        'email' => $existingAdmin->email,
        'creation_mode' => 'invite',
    ]);

    $response->assertSessionHasErrors('email');
    expect(Practitioner::query()->count())->toBe(0);
});

test('check-account endpoint reports new/existing/taken correctly', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $existingAdmin = actingAsAdmin();

    $newCheck = $this->actingAs($superAdmin)->getJson(
        route('admin.practitioners.check-account', ['email' => 'brand-new@example.com']),
    );
    $newCheck->assertOk();
    expect($newCheck->json('status'))->toBe('new');

    $takenCheck = $this->actingAs($superAdmin)->getJson(
        route('admin.practitioners.check-account', ['email' => $existingAdmin->email]),
    );
    $takenCheck->assertOk();
    expect($takenCheck->json('status'))->toBe('taken');

    // Now register a real practitioner with access, then check "existing".
    Notification::fake();
    $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'first_name' => 'Existing',
        'last_name' => 'Doc',
        'center_id' => $center->id,
        'matricule' => '006',
        'grant_access' => true,
        'email' => 'existing-doc@example.com',
        'creation_mode' => 'invite',
    ]);

    $existingCheck = $this->actingAs($superAdmin)->getJson(
        route('admin.practitioners.check-account', ['email' => 'existing-doc@example.com']),
    );
    $existingCheck->assertOk();
    expect($existingCheck->json('status'))->toBe('existing');
    expect($existingCheck->json('practitioner_name'))->toBe('Existing Doc');
});
