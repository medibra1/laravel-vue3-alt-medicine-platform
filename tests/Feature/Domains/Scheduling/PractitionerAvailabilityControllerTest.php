<?php

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
