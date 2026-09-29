<?php

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\PractitionerAvailability;

test('guests are redirected to login', function () {
    $this->get(route('admin.availabilities.index'))
        ->assertRedirect(route('login'));
});

test('super admin can create a recurring availability window', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.availabilities.store'), [
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 4,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $response->assertRedirect(route('admin.availabilities.index'));
    expect(PractitionerAvailability::query()->where('practitioner_id', $practitioner->id)->count())->toBe(1);
});

test('end_time must be after start_time', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.availabilities.store'), [
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 4,
        'start_time' => '12:00',
        'end_time' => '09:00',
    ]);

    $response->assertSessionHasErrors('end_time');
});

test('a manager cannot add availability for a practitioner in another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.availabilities.store'), [
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 4,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $response->assertSessionHasErrors('practitioner_id');
});

test('super admin can delete an availability window', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();
    $availability = PractitionerAvailability::query()->create([
        'practitioner_id' => $practitioner->id,
        'day_of_week' => 4,
        'start_time' => '09:00',
        'end_time' => '12:00',
    ]);

    $response = $this->actingAs($superAdmin)->delete(route('admin.availabilities.destroy', $availability));

    $response->assertRedirect(route('admin.availabilities.index'));
    expect(PractitionerAvailability::query()->whereKey($availability->id)->exists())->toBeFalse();
});

test('sync replaces a practitioner schedule, allowing a lunch break on the same day', function () {
    $center = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($center, 'center')->create();
    $manager = actingAsManagerOf($center);

    $response = $this->actingAs($manager)->put(route('admin.practitioners.availabilities.sync', $practitioner), [
        'slots' => [
            ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00'],
            ['day_of_week' => 1, 'start_time' => '14:00', 'end_time' => '18:00'],
        ],
    ]);

    $response->assertRedirect(route('admin.availabilities.index'));
    expect($practitioner->availabilities()->count())->toBe(2);
});

test('sync rejects overlapping windows on the same day', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();

    $response = $this->actingAs($superAdmin)->put(route('admin.practitioners.availabilities.sync', $practitioner), [
        'slots' => [
            ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '13:00'],
            ['day_of_week' => 1, 'start_time' => '12:00', 'end_time' => '17:00'],
        ],
    ]);

    $response->assertSessionHasErrors('slots.1.start_time');
    expect($practitioner->availabilities()->count())->toBe(0);
});

test('sync is forbidden for a practitioner of another center', function () {
    $otherCenter = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($otherCenter, 'center')->create();
    $manager = actingAsManagerOf(Center::factory()->create());

    $this->actingAs($manager)->put(route('admin.practitioners.availabilities.sync', $practitioner), [
        'slots' => [['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00']],
    ])->assertForbidden();
});

test('super admin can sync a practitioner of any center', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();

    $this->actingAs($superAdmin)->put(route('admin.practitioners.availabilities.sync', $practitioner), [
        'slots' => [['day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '17:00']],
    ])->assertRedirect(route('admin.availabilities.index'));

    expect($practitioner->availabilities()->count())->toBe(1);
});

test('sync is forbidden without the appointments.update permission', function () {
    $practitioner = Practitioner::factory()->create();

    $this->actingAs(User::factory()->create())->put(route('admin.practitioners.availabilities.sync', $practitioner), [
        'slots' => [],
    ])->assertForbidden();
});

test('bulk sync applies the schedule to every selected practitioner', function () {
    $center = Center::factory()->create();
    $practitioners = Practitioner::factory()->count(2)->for($center, 'center')->create();
    $manager = actingAsManagerOf($center);

    $this->actingAs($manager)->put(route('admin.practitioners.availabilities.bulk-sync'), [
        'practitioner_ids' => $practitioners->pluck('id')->all(),
        'slots' => [['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00']],
    ])->assertRedirect(route('admin.availabilities.index'));

    foreach ($practitioners as $practitioner) {
        expect($practitioner->availabilities()->count())->toBe(1);
    }
});

test('bulk sync rejects a practitioner outside the managed center and changes nothing', function () {
    $center = Center::factory()->create();
    $own = Practitioner::factory()->for($center, 'center')->create();
    $foreign = Practitioner::factory()->for(Center::factory()->create(), 'center')->create();
    $manager = actingAsManagerOf($center);

    $this->actingAs($manager)->put(route('admin.practitioners.availabilities.bulk-sync'), [
        'practitioner_ids' => [$own->id, $foreign->id],
        'slots' => [['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00']],
    ])->assertSessionHasErrors('practitioner_ids');

    expect(PractitionerAvailability::query()->count())->toBe(0);
});

test('bulk sync rejects overlapping windows', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();

    $this->actingAs($superAdmin)->put(route('admin.practitioners.availabilities.bulk-sync'), [
        'practitioner_ids' => [$practitioner->id],
        'slots' => [
            ['day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '12:00'],
            ['day_of_week' => 3, 'start_time' => '11:00', 'end_time' => '15:00'],
        ],
    ])->assertSessionHasErrors('slots.1.start_time');
});

test('bulk sync requires at least one practitioner', function () {
    $superAdmin = actingAsSuperAdmin();

    $this->actingAs($superAdmin)->put(route('admin.practitioners.availabilities.bulk-sync'), [
        'practitioner_ids' => [],
        'slots' => [],
    ])->assertSessionHasErrors('practitioner_ids');
});
