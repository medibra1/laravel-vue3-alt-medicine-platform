<?php

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Services\BulkSyncPractitionerAvailabilitiesAction;
use App\Domains\Scheduling\Services\SyncPractitionerAvailabilitiesAction;
use Illuminate\Database\QueryException;

test('each practitioner gets independent rows that can be customised afterwards', function () {
    $practitioners = Practitioner::factory()->count(2)->create();

    app(BulkSyncPractitionerAvailabilitiesAction::class)->handle($practitioners, [
        ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '17:00'],
    ]);

    [$first, $second] = $practitioners->all();
    app(SyncPractitionerAvailabilitiesAction::class)->handle($first, [
        ['day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '12:00'],
    ]);

    expect($first->availabilities()->pluck('day_of_week')->all())->toBe([2])
        ->and($second->availabilities()->pluck('day_of_week')->all())->toBe([1]);
});

test('a failure on one practitioner leaves every practitioner untouched', function () {
    $practitioners = Practitioner::factory()->count(2)->create();
    foreach ($practitioners as $practitioner) {
        $practitioner->availabilities()->create(['day_of_week' => 5, 'start_time' => '10:00', 'end_time' => '11:00']);
    }

    expect(fn () => app(BulkSyncPractitionerAvailabilitiesAction::class)->handle($practitioners, [
        ['day_of_week' => 1, 'start_time' => '09:00', 'end_time' => null],
    ]))->toThrow(QueryException::class);

    foreach ($practitioners as $practitioner) {
        expect($practitioner->availabilities()->pluck('day_of_week')->all())->toBe([5]);
    }
});
