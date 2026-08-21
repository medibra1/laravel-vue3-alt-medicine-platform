<?php

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Country;
use App\Domains\Practitioners\Models\Practitioner;

test('guests are redirected to login', function () {
    $this->get(route('admin.practitioners.index'))->assertRedirect(route('login'));
});

test('super admin can list practitioners from every center', function () {
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    Practitioner::factory()->for($centerA)->create();
    Practitioner::factory()->for($centerB)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.practitioners.index'));

    $response->assertOk();
    expect($response->inertiaPage()['props']['practitioners']['data'])->toHaveCount(2);
});

test('manager only sees practitioners from their own center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    Practitioner::factory()->for($ownCenter)->create();
    Practitioner::factory()->for($otherCenter)->create();

    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->get(route('admin.practitioners.index'));

    $response->assertOk();
    $data = $response->inertiaPage()['props']['practitioners']['data'];
    expect($data)->toHaveCount(1);
    expect($data[0]['center_id'])->toBe($ownCenter->id);
});

test('super admin can create a practitioner and full_code is generated correctly', function () {
    $superAdmin = actingAsSuperAdmin();
    $country = Country::factory()->create(['code' => '01']);
    $center = Center::factory()->for($country)->create(['code' => '02']);

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'center_id' => $center->id,
        'matricule' => '007',
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));

    $practitioner = Practitioner::query()->where('center_id', $center->id)->firstOrFail();
    expect($practitioner->full_code)->toBe('0102007');
    expect($practitioner->matricule)->toBe('007');
});

test('manager creating a practitioner is scoped to their own center regardless of payload', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    // center_id is 'prohibited' for managers — sending one at all is a
    // validation error, not a silent override.
    $response = $this->actingAs($manager)->post(route('admin.practitioners.store'), [
        'center_id' => $otherCenter->id,
        'matricule' => '010',
    ]);

    $response->assertSessionHasErrors('center_id');
    expect(Practitioner::query()->count())->toBe(0);
});

test('manager can create a practitioner without sending a center_id', function () {
    $ownCenter = Center::factory()->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->post(route('admin.practitioners.store'), [
        'matricule' => '011',
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));
    $practitioner = Practitioner::query()->firstOrFail();
    expect($practitioner->center_id)->toBe($ownCenter->id);
});

test('matricule must be exactly three digits', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'center_id' => $center->id,
        'matricule' => '12',
    ]);

    $response->assertSessionHasErrors('matricule');
});

test('creating a duplicate matricule within the same center fails validation', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    Practitioner::factory()->for($center)->create(['matricule' => '123']);

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'center_id' => $center->id,
        'matricule' => '123',
    ]);

    $response->assertSessionHasErrors('matricule');
    expect(Practitioner::query()->count())->toBe(1);
});

test('the same matricule is allowed in a different center', function () {
    $superAdmin = actingAsSuperAdmin();
    $centerA = Center::factory()->create();
    $centerB = Center::factory()->create();
    Practitioner::factory()->for($centerA)->create(['matricule' => '123']);

    $response = $this->actingAs($superAdmin)->post(route('admin.practitioners.store'), [
        'center_id' => $centerB->id,
        'matricule' => '123',
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));
    expect(Practitioner::query()->count())->toBe(2);
});

test('next-matricule endpoint suggests the next free matricule for a center', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    Practitioner::factory()->for($center)->create(['matricule' => '001']);
    Practitioner::factory()->for($center)->create(['matricule' => '002']);

    $response = $this->actingAs($superAdmin)->getJson(
        route('admin.practitioners.next-matricule', ['center_id' => $center->id]),
    );

    $response->assertOk();
    expect($response->json('matricule'))->toBe('003');
});

test('manager cannot update a practitioner from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($otherCenter)->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->put(route('admin.practitioners.update', $practitioner), [
        'matricule' => $practitioner->matricule,
    ]);

    $response->assertForbidden();
});

test('manager can update a practitioner in their own center', function () {
    $ownCenter = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($ownCenter)->create(['level' => null]);
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->put(route('admin.practitioners.update', $practitioner), [
        'matricule' => $practitioner->matricule,
        'level' => 3,
    ]);

    $response->assertRedirect(route('admin.practitioners.index'));
    expect($practitioner->fresh()->level)->toBe(3);
});

test('manager cannot delete a practitioner from another center', function () {
    $ownCenter = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $practitioner = Practitioner::factory()->for($otherCenter)->create();
    $manager = actingAsManagerOf($ownCenter);

    $response = $this->actingAs($manager)->delete(route('admin.practitioners.destroy', $practitioner));

    $response->assertForbidden();
    expect(Practitioner::query()->whereKey($practitioner->id)->exists())->toBeTrue();
});

test('super admin can delete any practitioner', function () {
    $superAdmin = actingAsSuperAdmin();
    $practitioner = Practitioner::factory()->create();

    $response = $this->actingAs($superAdmin)->delete(route('admin.practitioners.destroy', $practitioner));

    $response->assertRedirect(route('admin.practitioners.index'));
    expect(Practitioner::query()->whereKey($practitioner->id)->exists())->toBeFalse();
});
