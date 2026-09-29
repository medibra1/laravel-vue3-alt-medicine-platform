<?php

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterClosure;
use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\Appointment;

function closurePayload(array $overrides = []): array
{
    return [
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-13',
        'label' => 'Fête nationale',
        ...$overrides,
    ];
}

test('a manager can create and delete a closure of their center', function () {
    $center = Center::factory()->create();
    $manager = actingAsManagerOf($center);

    $this->actingAs($manager)
        ->post(route('admin.centers.closures.store', $center), closurePayload())
        ->assertSessionHasNoErrors();

    $closure = CenterClosure::query()->sole();
    expect($closure->center_id)->toBe($center->id);
    expect($closure->created_by)->toBe($manager->id);

    $this->actingAs($manager)->getJson(route('admin.centers.closures.index', $center))
        ->assertOk()->assertJsonCount(1);

    $this->actingAs($manager)->delete(route('admin.closures.destroy', $closure))->assertRedirect();
    expect(CenterClosure::query()->count())->toBe(0);
});

test('a manager cannot manage closures of another center', function () {
    $otherCenter = Center::factory()->create();
    $manager = actingAsManagerOf(Center::factory()->create());
    $closure = CenterClosure::query()->create([...closurePayload(), 'center_id' => $otherCenter->id, 'created_by' => $manager->id]);

    $this->actingAs($manager)->post(route('admin.centers.closures.store', $otherCenter), closurePayload())->assertForbidden();
    $this->actingAs($manager)->getJson(route('admin.centers.closures.index', $otherCenter))->assertForbidden();
    $this->actingAs($manager)->delete(route('admin.closures.destroy', $closure))->assertForbidden();

    expect(CenterClosure::query()->count())->toBe(1);
});

test('ends_on before starts_on and a missing label are rejected', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.centers.closures.store', $center), closurePayload(['ends_on' => '2026-10-10', 'label' => '']))
        ->assertSessionHasErrors(['ends_on', 'label']);
});

test('creating a closure flashes affected appointments across every practitioner of the center', function () {
    $superAdmin = actingAsSuperAdmin();
    $center = Center::factory()->create();
    $make = function (Center $c, string $startsAt, string $status = 'scheduled') use ($superAdmin) {
        $practitioner = Practitioner::factory()->for($c, 'center')->create();

        return Appointment::query()->create([
            'center_id' => $c->id,
            'practitioner_id' => $practitioner->id,
            'patient_id' => Patient::factory()->create(['intake_center_id' => $c->id])->id,
            'starts_at' => $startsAt,
            'duration_minutes' => 30,
            'status' => $status,
            'created_by' => $superAdmin->id,
        ]);
    };
    $make($center, '2026-10-12 09:00:00');
    $make($center, '2026-10-13 17:30:00', 'confirmed'); // other practitioner, last day
    $make($center, '2026-10-12 11:00:00', 'cancelled'); // not counted
    $make(Center::factory()->create(), '2026-10-12 09:00:00'); // other center

    $this->actingAs($superAdmin)
        ->post(route('admin.centers.closures.store', $center), closurePayload())
        ->assertSessionHas('time_off_affected_appointments', 2);

    expect(Appointment::query()->count())->toBe(4);
});
